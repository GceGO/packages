<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Jobs\DeliverScheduledReport;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Models\ScheduledReport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Scheduling\ReportScheduleManager;
use Gcego\Analytics\Tests\Fixtures\Models\Membership;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

function schedules(): ReportScheduleManager
{
    return app(ReportScheduleManager::class);
}

function scheduleWeekly(array $recipients = []): ScheduledReport
{
    return schedules()->create(app(AnalyticsDefinitions::class)->report('subject-performance'), analytics()->context(), 'weekly', '07:30', $recipients, ['level' => 'o'], 'last_7_days', day: 1);
}

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('creates schedules for eligible owners and recipients only', function (): void {
    $schedule = scheduleWeekly([$this->world['admin']]);

    expect($schedule->next_run_at?->toIso8601String())->toBe(CarbonImmutable::parse('2026-03-16 07:30:00', 'UTC')->toIso8601String())
        ->and($schedule->recipients)->toBe([$this->world['admin']->getMorphClass().'#'.$this->world['admin']->id])
        ->and(fn () => scheduleWeekly([$this->world['student1']]))->toThrow(AnalyticsQueryException::class, 'recipient')
        ->and(fn () => scheduleWeekly([$this->world['south']]))->toThrow(AnalyticsQueryException::class, 'recipient')
        ->and(fn () => schedules()->create(app(AnalyticsDefinitions::class)->report('subject-performance'), analytics()->context(), 'hourly', '07:30'))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => schedules()->create(app(AnalyticsDefinitions::class)->report('subject-performance'), analytics()->context(), 'daily', '25:00'))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => schedules()->create(app(AnalyticsDefinitions::class)->report('subject-performance'), analytics()->context(), 'daily', '07:00', range: 'custom'))->toThrow(AnalyticsQueryException::class);

    actAs($this->world['student1']);
    expect(fn () => scheduleWeekly())->toThrow(AnalyticsAuthorizationException::class);
});

it('computes next runs in the schedule\'s time zone', function (string $frequency, ?int $day, string $timezone, string $expected): void {
    $schedule = (new ScheduledReport)->forceFill(['frequency' => $frequency, 'day' => $day, 'time' => '07:30', 'timezone' => $timezone]);

    expect(ReportScheduleManager::nextRun($schedule, CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'))->toIso8601String())->toBe(CarbonImmutable::parse($expected, 'UTC')->toIso8601String());
})->with([
    'daily' => ['daily', null, 'UTC', '2026-03-16 07:30'],
    'daily in Douala' => ['daily', null, 'Africa/Douala', '2026-03-16 06:30'],
    'weekly on Friday' => ['weekly', 5, 'UTC', '2026-03-20 07:30'],
    'monthly on the 1st' => ['monthly', 1, 'UTC', '2026-04-01 07:30'],
]);

it('queues each due run once, then delivers to recipients who may still see the report', function (): void {
    $schedule = scheduleWeekly([$this->world['admin']]);
    Queue::fake([DeliverScheduledReport::class]);
    $this->travelTo(CarbonImmutable::parse('2026-03-16 07:31:00', 'UTC'));

    $this->artisan('gcego:analytics:run-schedules')->expectsOutputToContain('Queued 1')->assertSuccessful();
    $this->artisan('gcego:analytics:run-schedules')->expectsOutputToContain('Queued 0')->assertSuccessful();
    Queue::assertPushed(DeliverScheduledReport::class, 1);

    expect($schedule->refresh()->next_run_at?->toDateString())->toBe('2026-03-23');

    (new DeliverScheduledReport($schedule->id, 'run'))->handle(schedules());
    $export = AnalyticsExport::query()->where('schedule_id', $schedule->id)->firstOrFail();

    expect($export->status)->toBe(AnalyticsExport::Completed)->and($export->rows)->toBe(2)
        ->and($export->recipients)->toBe([$this->world['admin']->getMorphClass().'#'.$this->world['admin']->id])
        ->and($this->world['admin']->notifications()->where('data->title', 'gcego-analytics::messages.notifications.scheduled.title')->exists())->toBeTrue()
        ->and($this->world['teacher']->notifications()->exists())->toBeTrue()
        ->and($schedule->refresh()->last_status)->toBe('delivered');
});

it('skips recipients who lost access and pauses schedules whose owner did', function (): void {
    $schedule = scheduleWeekly([$this->world['admin']]);
    Membership::query()->where('user_id', $this->world['admin']->id)->delete();

    schedules()->deliver($schedule);
    expect($schedule->refresh()->last_status)->toBe('delivered: 1 skipped')->and($this->world['admin']->notifications()->exists())->toBeFalse();

    Membership::query()->where('user_id', $this->world['teacher']->id)->delete();
    app()->forgetScopedInstances();   // each delivery is its own job
    schedules()->deliver($schedule);

    expect($schedule->refresh()->active)->toBeFalse()->and($schedule->last_status)->toBe('paused: access')
        ->and(AnalyticsExport::query()->where('schedule_id', $schedule->id)->count())->toBe(1);
});

it('lets owners cancel their schedules only', function (): void {
    $schedule = scheduleWeekly();
    actAs($this->world['admin']);
    expect(fn () => schedules()->cancel($schedule, analytics()->context()))->toThrow(AnalyticsAuthorizationException::class);

    actAs($this->world['teacher']);
    schedules()->cancel($schedule, analytics()->context());
    expect($schedule->refresh()->active)->toBeFalse();
});

it('registers its commands with Laravel\'s scheduler unless disabled', function (): void {
    $commands = static fn (): array => collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command)->filter(fn (string $command) => str_contains($command, 'gcego:analytics'))->values()->all();

    expect($commands())->toHaveCount(2);

    $this->reboot(['gcego-analytics.scheduled_reports.schedule' => false]);
    expect($commands())->toBe([]);
});
