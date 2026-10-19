<?php

declare(strict_types=1);

namespace Gcego\Analytics\Scheduling;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Events\ScheduledReportDelivered;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Filters\FilterResolver;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Jobs\DeliverScheduledReport;
use Gcego\Analytics\Models\ScheduledReport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Support\Keys;
use Gcego\Security\Exceptions\SecurityException;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scheduled reports, on Laravel's scheduler (gcego:analytics:run-schedules):
 *
 * - create(): the owner must be allowed to schedule the report; filters
 *   and the period preset are validated; every recipient must be a member
 *   of the tenant and allowed to see the report;
 * - due(): claims each due schedule atomically (its next run moves first,
 *   so a run is never queued twice) and queues its delivery;
 * - deliver(): as the owner in the stored tenant (membership and access
 *   re-verified; a schedule whose owner lost access is paused), generates
 *   the export and notifies each recipient who may still see the report.
 */
final readonly class ReportScheduleManager
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private ReportManager $reports,
        private AnalyticsAuthorizer $authorizer,
        private ExportManager $exports,
        private AnalyticsNotifier $notifier,
        private AnalyticsConfig $config,
        private Container $container,
    ) {}

    /**
     * @param  array<array-key, mixed>  $filters
     * @param  list<Model>  $recipients
     */
    public function create(Report $report, AnalyticsContext $owner, string $frequency, string $time, array $recipients = [], array $filters = [], ?string $range = null, string $format = 'csv', ?int $day = null): ScheduledReport
    {
        $user = $owner->user instanceof Model ? $owner->user : throw AnalyticsAuthorizationException::for('schedule reports');
        $dataset = $this->reports->authorize($report, $owner);

        if (! $this->authorizer->schedule($report, $dataset, $owner)) {
            throw AnalyticsAuthorizationException::for('schedule this report');
        }

        if (! in_array($frequency, ScheduledReport::Frequencies, true)) {
            throw AnalyticsQueryException::rejected('schedule', 'Choose daily, weekly or monthly.');
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw AnalyticsQueryException::rejected('schedule', 'Use a time such as 07:30.');
        }

        if (($frequency === 'weekly' && ($day === null || $day < 1 || $day > 7)) || ($frequency === 'monthly' && ($day === null || $day < 1 || $day > 28)) || ($frequency === 'daily' && $day !== null)) {
            throw AnalyticsQueryException::rejected('schedule', 'Choose a weekday (1–7) for weekly reports or a day (1–28) for monthly reports.');
        }

        if ($range !== null && (DateRangePreset::tryFrom($range) === null || $range === DateRangePreset::Custom->value)) {
            throw AnalyticsQueryException::rejected('date_range', 'Scheduled reports use a relative period such as last_7_days.');
        }

        if (! in_array($format, $report->formats(), true)) {
            throw AnalyticsQueryException::rejected('format', 'That format is not available for this report.');
        }

        FilterResolver::resolve($report->filters(), $filters);

        $keys = [];

        foreach ($recipients as $recipient) {
            if (! $this->mayReceive($report, $recipient, $owner->tenant, $owner->timezone)) {
                throw AnalyticsQueryException::rejected('recipient', 'Every recipient must be allowed to see this report.');
            }

            $keys[] = $recipient->getMorphClass().'#'.Keys::string($recipient->getKey());
        }

        $schedule = new ScheduledReport;
        $schedule->forceFill([
            'ulid' => (string) Str::ulid(),
            'report' => $report->nodeName(),
            'owner_type' => $user->getMorphClass(),
            'owner_id' => Keys::string($user->getKey()),
            'tenant' => $owner->tenant,
            'filters' => $filters === [] ? null : $filters,
            'range' => $range,
            'format' => $format,
            'frequency' => $frequency,
            'day' => $day,
            'time' => $time,
            'timezone' => $owner->timezone,
            'recipients' => array_values(array_unique($keys)),
            'active' => true,
        ]);
        $schedule->forceFill(['next_run_at' => self::nextRun($schedule, CarbonImmutable::now())])->save();

        return $schedule;
    }

    public function cancel(ScheduledReport $schedule, AnalyticsContext $context): void
    {
        $user = $context->user instanceof Model ? $context->user : null;

        if (! $schedule->ownedBy($user) || $schedule->tenant !== $context->tenant) {
            throw AnalyticsAuthorizationException::for('cancel this schedule');
        }

        $schedule->forceFill(['active' => false, 'next_run_at' => null, 'last_status' => 'cancelled'])->save();
    }

    /**
     * Claims and queues due schedules; returns how many were queued.
     */
    public function due(): int
    {
        if (! $this->config->schedules) {
            return 0;
        }

        $queued = 0;

        foreach (ScheduledReport::query()->where('active', true)->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit($this->config->scheduleBatch)->get() as $schedule) {
            $previous = $schedule->next_run_at;
            $next = self::nextRun($schedule, CarbonImmutable::now());
            $claimed = ScheduledReport::query()->whereKey($schedule->id)->where('active', true)->where('next_run_at', $previous)->update(['next_run_at' => $next, 'last_run_at' => now(), 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            $this->container->make(Bus::class)->dispatch((new DeliverScheduledReport($schedule->id, (string) $previous?->toIso8601String()))->onQueue($this->config->exportQueue)->onConnection($this->config->exportConnection));
            $queued++;
        }

        return $queued;
    }

    public function deliver(ScheduledReport $schedule): void
    {
        if (! $schedule->active) {
            return;
        }

        $owner = $this->person($schedule->owner_type, $schedule->owner_id);

        try {
            $report = $this->definitions->report($schedule->report);
            $context = new AnalyticsContext($owner instanceof Authenticatable ? $owner : null, $schedule->tenant, $schedule->timezone);
            $dataset = $this->reports->authorize($report, $context);

            if (! $owner instanceof Model || ! $this->authorizer->schedule($report, $dataset, $context)) {
                throw AnalyticsAuthorizationException::for('schedule this report');
            }
        } catch (AnalyticsAuthorizationException|AnalyticsNotFoundException|SecurityException $exception) {
            // The owner can no longer run it (left the tenant, lost access, report removed): pause, do not deliver.
            $schedule->forceFill(['active' => false, 'next_run_at' => null, 'last_status' => 'paused: access', 'failures' => $schedule->failures + 1])->save();

            return;
        }

        $eligible = [];
        $skipped = 0;

        foreach ($schedule->recipients as $key) {
            [$type, $id] = array_pad(explode('#', $key, 2), 2, '');
            $recipient = $this->person($type, $id);

            if ($recipient !== null && $this->mayReceive($report, $recipient, $schedule->tenant, $schedule->timezone)) {
                $eligible[$key] = $recipient;
            } else {
                $skipped++;
            }
        }

        $range = $schedule->range === null ? null : DateRange::preset($schedule->range, $schedule->timezone);

        try {
            $export = $this->exports->request($report, new ReportRequest($schedule->filters ?? [], $range, defaultRange: $range === null), $schedule->format, $context, array_keys($eligible), $schedule->id, false);
        } catch (AnalyticsAuthorizationException|SecurityException) {
            // Access ended while the run was being prepared: pause rather than deliver.
            $schedule->forceFill(['active' => false, 'next_run_at' => null, 'last_status' => 'paused: access', 'failures' => $schedule->failures + 1])->save();

            return;
        }

        foreach ([$owner, ...array_values($eligible)] as $person) {
            $this->notifier->scheduled($export, $person, $report);
        }

        $schedule->forceFill(['last_status' => $skipped > 0 ? 'delivered: '.$skipped.' skipped' : 'delivered', 'failures' => 0])->save();
        $this->container->make(Dispatcher::class)->dispatch(new ScheduledReportDelivered($schedule->ulid, $report->nodeName(), $schedule->tenant, count($eligible) + 1, $skipped));
    }

    public function failed(int $schedule, Throwable $exception): void
    {
        ScheduledReport::query()->whereKey($schedule)->increment('failures', 1, ['last_status' => 'failed: '.class_basename($exception)]);
    }

    public static function nextRun(ScheduledReport $schedule, CarbonImmutable $after): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $schedule->time));
        $local = $after->setTimezone($schedule->timezone);
        $candidate = $local->setTime($hour, $minute);

        $candidate = match ($schedule->frequency) {
            'weekly' => $candidate->addDays((($schedule->day ?? 1) - $candidate->dayOfWeekIso + 7) % 7),
            'monthly' => $candidate->setDay($schedule->day ?? 1),
            default => $candidate,
        };

        while ($candidate <= $local) {
            $candidate = match ($schedule->frequency) {
                'weekly' => $candidate->addWeek(),
                'monthly' => $candidate->addMonthNoOverflow()->setDay($schedule->day ?? 1),
                default => $candidate->addDay(),
            };
        }

        return $candidate->setTimezone(Keys::string(config('app.timezone', 'UTC')) ?: 'UTC');
    }

    private function mayReceive(Report $report, Model $recipient, ?string $tenant, string $timezone): bool
    {
        if (! $recipient instanceof Authenticatable) {
            return false;
        }

        try {
            return $this->reports->allows($report, new AnalyticsContext($recipient, $tenant, $timezone))
                && ($tenant === null || $this->container->make(SecurityManager::class)->actingAs($recipient, $tenant, static fn (): bool => true));
        } catch (Throwable) {
            return false;
        }
    }

    private function person(string $type, string $id): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return $id !== '' && is_subclass_of($class, Model::class) ? $class::query()->find($id) : null;
    }
}
