<?php

declare(strict_types=1);

namespace Gcego\Analytics\Console;

use Gcego\Analytics\Diagnostics\AnalyticsInspector;
use Illuminate\Console\Command;

final class InspectAnalyticsCommand extends Command
{
    protected $signature = 'gcego:analytics:inspect {--json : Output JSON} {--definitions : Definitions only (no database)}';

    protected $description = 'Inspect analytics datasets, metrics, reports, dashboards and their configuration';

    public function handle(AnalyticsInspector $inspector): int
    {
        $report = $inspector->report(! $this->option('definitions'));
        $failed = false;

        foreach ($report['definitions'] as $definitions) {
            foreach ($definitions as $definition) {
                $failed = $failed || $definition['problems'] !== [];
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        foreach ($report['definitions'] as $kind => $definitions) {
            $this->components->twoColumnDetail('<fg=green>'.$kind.'</>', (string) count($definitions));

            foreach ($definitions as $definition) {
                $this->components->twoColumnDetail('  '.$definition['name'], $definition['problems'] === [] ? '<fg=green>ok</>' : '<fg=red>'.count($definition['problems']).' problem(s)</>');

                foreach ([...$definition['problems'], ...array_map(static fn (string $warning): string => 'warning: '.$warning, $definition['warnings'])] as $line) {
                    $this->line('    - '.$line);
                }
            }
        }

        foreach ([...$report['config'], ...$report['runtime']] as $key => $value) {
            $this->components->twoColumnDetail($key, is_array($value) ? (string) json_encode($value) : (is_scalar($value) ? (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value) : ''));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
