<?php

declare(strict_types=1);

namespace Gcego\Analytics\Console;

use Gcego\Analytics\Scheduling\ReportScheduleManager;
use Illuminate\Console\Command;

/**
 * Queues due scheduled reports (registered on Laravel's scheduler every five minutes).
 */
final class RunSchedulesCommand extends Command
{
    protected $signature = 'gcego:analytics:run-schedules';

    protected $description = 'Queue due scheduled analytics reports';

    public function handle(ReportScheduleManager $schedules): int
    {
        $this->components->info(sprintf('Queued %d scheduled report(s).', $schedules->due()));

        return self::SUCCESS;
    }
}
