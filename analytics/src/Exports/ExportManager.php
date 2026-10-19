<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exports;

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Events\ExportCompleted;
use Gcego\Analytics\Events\ExportFailed;
use Gcego\Analytics\Events\ExportStarted;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Integration\AnalyticsNotifier;
use Gcego\Analytics\Jobs\GenerateAnalyticsReport;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportManager;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Security\AnalyticsAuthorizer;
use Gcego\Analytics\Security\AnalyticsContext;
use Gcego\Analytics\Support\Keys;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Report exports:
 *
 * - request(): authorizes (report access, Export flag, ExportAbility),
 *   checks the format, the viewer's running exports (max_concurrent) and
 *   the row count (max_rows), records the export, then generates it in
 *   the request (up to sync_rows) or queues it (after the commit);
 * - generate(): re-authorizes the owner in the export's tenant, reads the
 *   report in chunks (bounded memory), writes CSV/XLSX to a private disk
 *   under an unguessable path, and notifies the owner of queued exports;
 * - download(): owner or listed recipient, same tenant, report access
 *   re-checked, not expired; safe headers and filename;
 * - prune(): deletes expired files.
 */
final readonly class ExportManager
{
    public function __construct(
        private AnalyticsDefinitions $definitions,
        private ReportManager $reports,
        private AnalyticsAuthorizer $authorizer,
        private AnalyticsConfig $config,
        private Filesystems $filesystems,
        private AnalyticsNotifier $notifier,
        private Container $container,
    ) {}

    /**
     * @param  list<string>  $recipients  "morph#key" of people (besides the owner) who may download it.
     */
    public function request(Report $report, ReportRequest $request, string $format, AnalyticsContext $context, array $recipients = [], ?int $schedule = null, bool $queue = true): AnalyticsExport
    {
        $owner = $context->user instanceof Model ? $context->user : throw AnalyticsAuthorizationException::for('export reports');
        $dataset = $this->reports->authorize($report, $context);

        if (! $this->authorizer->export($report, $dataset, $context)) {
            throw AnalyticsAuthorizationException::for('export this report');
        }

        if (! in_array($format, $report->formats(), true) || ! ExportWriter::supports($format)) {
            throw AnalyticsQueryException::rejected('format', 'That format is not available for this report.');
        }

        $running = AnalyticsExport::query()->where('owner_type', $owner->getMorphClass())->where('owner_id', Keys::string($owner->getKey()))->whereIn('status', [AnalyticsExport::Pending, AnalyticsExport::Running])->count();

        if ($schedule === null && $running >= $this->config->exportMaxConcurrent) {
            throw AnalyticsQueryException::rejected('export_limit', (string) __('gcego-analytics::messages.errors.export_limit'));
        }

        // Validates filters, period and sort now, and counts the rows.
        $probe = $this->reports->run($report, new ReportRequest($request->filters, $request->range, $request->sort, $request->direction, 1, 1, $request->defaultRange, true), $context);

        if ($probe->total > $this->config->exportMaxRows) {
            throw AnalyticsQueryException::rejected('export_rows', (string) __('gcego-analytics::messages.errors.export_rows', ['max' => $this->config->exportMaxRows]));
        }

        $export = new AnalyticsExport;
        $export->forceFill([
            'ulid' => (string) Str::ulid(),
            'report' => $report->nodeName(),
            'format' => $format,
            'status' => AnalyticsExport::Pending,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => Keys::string($owner->getKey()),
            'tenant' => $context->tenant,
            'request' => ['filters' => $request->filters, 'range' => $probe->range?->toArray(), 'sort' => $probe->sort, 'direction' => $probe->direction, 'timezone' => $context->timezone],
            'recipients' => array_values(array_unique($recipients)),
            'schedule_id' => $schedule,
            'disk' => $this->config->exportDisk,
            'rows' => $probe->total,
        ])->save();

        $this->container->make(Dispatcher::class)->dispatch(new ExportStarted($export->ulid, $report->nodeName(), $format, $context->tenant, $probe->total, $context->correlation));

        if (! $queue || $probe->total <= $this->config->exportSyncRows) {
            $this->generate($export);

            return $export->refresh();
        }

        $job = (new GenerateAnalyticsReport($export->id))->onQueue($this->config->exportQueue)->onConnection($this->config->exportConnection)->afterCommit();
        $this->container->make(Bus::class)->dispatch($job);

        return $export;
    }

    public function generate(AnalyticsExport $export): void
    {
        $claimed = AnalyticsExport::query()->whereKey($export->id)->whereIn('status', [AnalyticsExport::Pending, AnalyticsExport::Failed])->update(['status' => AnalyticsExport::Running, 'started_at' => now(), 'error' => null, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $export->refresh();
        $temporary = tempnam(sys_get_temp_dir(), 'gcego-analytics-');

        try {
            $owner = $this->owner($export);
            $owner = $owner instanceof Authenticatable ? $owner : throw AnalyticsAuthorizationException::for('export this report');
            $timezone = is_string($export->request['timezone'] ?? null) ? $export->request['timezone'] : $this->config->timezone();
            $context = new AnalyticsContext($owner, $export->tenant, $timezone);
            $report = $this->definitions->report($export->report);
            $dataset = $this->reports->authorize($report, $context);

            if (! $this->authorizer->export($report, $dataset, $context)) {
                throw AnalyticsAuthorizationException::for('export this report');
            }

            $range = is_array($range = $export->request['range'] ?? null) && is_string($range['from'] ?? null) && is_string($range['to'] ?? null) ? DateRange::between($range['from'], $range['to'], $timezone) : null;
            $filters = is_array($export->request['filters'] ?? null) ? $export->request['filters'] : [];
            $sort = is_string($export->request['sort'] ?? null) ? $export->request['sort'] : null;
            $direction = is_string($export->request['direction'] ?? null) ? $export->request['direction'] : null;

            $writer = new ExportWriter($export->format, (string) $temporary);
            $page = 1;
            $written = 0;
            $header = false;

            do {
                $result = $this->reports->run($report, new ReportRequest($filters, $range, $sort, $direction, $page, $this->config->exportChunk, false, true), $context);

                if (! $header) {
                    $writer->row(array_map(static fn (array $column): string => $column['label'], $result->columns));
                    $header = true;
                }

                foreach ($result->rows as $row) {
                    $writer->row(array_map(static fn (array $column): int|float|string|null => $row['suppressed'] && $column['kind'] === 'measure' ? (string) __('gcego-analytics::messages.suppressed') : ($column['kind'] === 'measure' ? $row['cells'][$column['name']]['value'] ?? null : $row['cells'][$column['name']]['formatted'] ?? null), $result->columns));
                    $written++;
                }

                $page++;
            } while ($page <= $result->lastPage() && $written < $this->config->exportMaxRows);

            $writer->close();
            $path = $this->config->exportDirectory.'/'.substr(hash('sha256', 'tenant:'.($export->tenant ?? '-')), 0, 16).'/'.$export->ulid.'.'.ExportWriter::extension($export->format);
            $stream = fopen((string) $temporary, 'rb');

            if ($stream === false || ! $this->filesystems->disk($export->disk)->writeStream($path, $stream, ['visibility' => 'private'])) {
                throw new \RuntimeException('The export could not be stored.');
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $export->forceFill(['status' => AnalyticsExport::Completed, 'rows' => $written, 'path' => $path, 'size' => (int) filesize((string) $temporary), 'completed_at' => now(), 'expires_at' => CarbonImmutable::now()->addHours($this->config->exportTtlHours)])->save();
            $this->container->make(Dispatcher::class)->dispatch(new ExportCompleted($export->ulid, $export->report, $export->tenant, $written));

            if ($export->schedule_id === null && ($export->rows ?? 0) > $this->config->exportSyncRows) {
                $this->notifier->exportReady($export, $owner, $report);
            }
        } catch (Throwable $exception) {
            $export->forceFill(['status' => AnalyticsExport::Failed, 'error' => class_basename($exception)])->save();
            $this->container->make(Dispatcher::class)->dispatch(new ExportFailed($export->ulid, $export->report, $export->tenant, class_basename($exception)));
            report($exception);

            if (($owner ?? null) instanceof Model && isset($report)) {
                $this->notifier->exportFailed($export, $owner, $report);
            }

            throw $exception;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Streams an export to an authorized viewer.
     */
    public function download(string $ulid, AnalyticsContext $context): StreamedResponse
    {
        $export = preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $ulid) === 1 ? AnalyticsExport::query()->where('ulid', strtoupper($ulid))->first() : null;
        $user = $context->user instanceof Model ? $context->user : null;

        // Other tenants' exports, and exports the viewer is not part of, look missing.
        if ($export === null || $user === null || $export->tenant !== $context->tenant || (! $export->ownedBy($user) && ! $export->sentTo($user))) {
            throw AnalyticsNotFoundException::make('export');
        }

        if (! $export->isDownloadable()) {
            throw AnalyticsNotFoundException::make('export');
        }

        $report = $this->definitions->report($export->report);

        if (! $this->reports->allows($report, $context)) {
            throw AnalyticsAuthorizationException::for('download this report');
        }

        $disk = $this->filesystems->disk($export->disk);

        if (! $disk->exists((string) $export->path)) {
            throw AnalyticsNotFoundException::make('export');
        }

        $filename = Str::slug($report->title()).'-'.$export->completed_at?->format('Ymd-Hi').'.'.ExportWriter::extension($export->format);

        return new StreamedResponse(static function () use ($disk, $export): void {
            $stream = $disk->readStream((string) $export->path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => ExportWriter::mime($export->format),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    /**
     * Deletes expired export files (and exports past their retention).
     */
    public function prune(): int
    {
        $count = 0;

        foreach (AnalyticsExport::query()->where('status', AnalyticsExport::Completed)->where('expires_at', '<=', now())->limit(1000)->get() as $export) {
            if ($export->path !== null) {
                $this->filesystems->disk($export->disk)->delete($export->path);
            }

            $export->forceFill(['status' => AnalyticsExport::Expired, 'path' => null])->save();
            $count++;
        }

        AnalyticsExport::query()->whereIn('status', [AnalyticsExport::Expired, AnalyticsExport::Failed])->where('updated_at', '<=', now()->subDays(30))->delete();

        return $count;
    }

    public function owner(AnalyticsExport $export): ?Model
    {
        $class = Relation::getMorphedModel($export->owner_type) ?? $export->owner_type;

        return is_subclass_of($class, Model::class) ? $class::query()->find($export->owner_id) : null;
    }
}
