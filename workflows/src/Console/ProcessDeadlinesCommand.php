<?php

declare(strict_types=1);

namespace Gcego\Workflows\Console;

use Gcego\Workflows\Scheduling\DeadlineProcessor;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Console\Command;

/**
 * Processes due deadlines and overdue tasks (scheduled every five minutes when gcego-workflows.deadlines.schedule is on).
 */
final class ProcessDeadlinesCommand extends Command
{
    protected $signature = 'gcego:workflows:process-deadlines';

    protected $description = 'Run due workflow deadlines, escalations and overdue task reminders';

    public function handle(DeadlineProcessor $processor, WorkflowsConfig $config): int
    {
        if (! $config->deadlines) {
            $this->components->warn('Workflow deadlines are disabled (gcego-workflows.deadlines.enabled).');

            return self::SUCCESS;
        }

        $counts = $processor->process();
        $this->components->info(sprintf('Deadlines: %d, overdue tasks: %d, failed: %d.', $counts['deadlines'], $counts['overdue'], $counts['failed']));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
