<?php

declare(strict_types=1);

namespace Gcego\Analytics\Jobs;

use Gcego\Analytics\Models\ScheduledReport;
use Gcego\Analytics\Scheduling\ReportScheduleManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Delivers one run of a scheduled report (unique per schedule and run).
 */
final class DeliverScheduledReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public readonly int $schedule, public readonly string $run) {}

    public function uniqueId(): string
    {
        return $this->schedule.'@'.$this->run;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ReportScheduleManager $schedules): void
    {
        $schedule = ScheduledReport::query()->find($this->schedule);

        if ($schedule !== null) {
            $schedules->deliver($schedule);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ReportScheduleManager::class)->failed($this->schedule, $exception);
    }
}
