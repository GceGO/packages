<?php

declare(strict_types=1);

namespace Gcego\Studio\Console;

use Gcego\Studio\Health\HealthStatus;
use Gcego\Studio\Studio;
use Illuminate\Console\Command;

/**
 * php artisan gcego:studio:health [--production] [--json] — read-only checks; exit 1 on any failure.
 */
final class HealthCommand extends Command
{
    protected $signature = 'gcego:studio:health {--production : Add the production readiness checks} {--json : Output JSON}';

    protected $description = 'Check the health (and production readiness) of GCEGO Admin Studio';

    public function handle(Studio $studio): int
    {
        $report = $studio->health((bool) $this->option('production'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->healthy() ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('GCEGO Admin Studio — health');

        foreach ($report->results as $result) {
            $this->components->twoColumnDetail($result->label, match ($result->status) {
                HealthStatus::Pass => '<fg=green>PASS</>',
                HealthStatus::Warning => '<fg=yellow>WARNING</>',
                HealthStatus::Failure => '<fg=red>FAIL</>',
                HealthStatus::Skipped => '<fg=gray>SKIPPED</>',
            });

            if ($result->status !== HealthStatus::Pass) {
                $this->line('    '.$result->message);

                if ($result->remediation !== null) {
                    $this->line('    <fg=gray>→ '.$result->remediation.'</>');
                }
            }
        }

        $this->newLine();
        $this->line(sprintf('Overall: %s (%d passed, %d warnings, %d failed, %d skipped)', $report->healthy() ? '<fg=green>HEALTHY</>' : '<fg=red>UNHEALTHY</>', $report->count(HealthStatus::Pass), $report->count(HealthStatus::Warning), $report->count(HealthStatus::Failure), $report->count(HealthStatus::Skipped)));

        return $report->healthy() ? self::SUCCESS : self::FAILURE;
    }
}
