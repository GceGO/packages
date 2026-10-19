<?php

declare(strict_types=1);

namespace Gcego\Workflows\Console;

use Gcego\Workflows\Diagnostics\WorkflowInspector;
use Illuminate\Console\Command;

final class InspectWorkflowsCommand extends Command
{
    protected $signature = 'gcego:workflows:inspect {--json : Output JSON} {--definitions : Definitions only (no database)}';

    protected $description = 'Inspect workflow definitions, running instances and configuration';

    public function handle(WorkflowInspector $inspector): int
    {
        $report = $inspector->report(! $this->option('definitions'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->hasProblems($report) ? self::FAILURE : self::SUCCESS;
        }

        foreach ($report['workflows'] as $workflow) {
            $text = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';
            $this->components->twoColumnDetail('<fg=green>'.$text($workflow['name'] ?? '').'</>', 'v'.$text($workflow['version'] ?? '?').' '.$text($workflow['structure'] ?? ''));

            foreach (['states', 'transitions', 'approvals', 'unreachable', 'problems'] as $key) {
                $values = $workflow[$key] ?? [];

                if (is_array($values) && $values !== []) {
                    $this->components->twoColumnDetail('  '.$key, implode(', ', array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values)));
                }
            }
        }

        if ($report['workflows'] === []) {
            $this->components->info('No workflows are registered.');
        }

        foreach ($report['runtime'] as $key => $value) {
            $this->components->twoColumnDetail($key, is_array($value) ? (string) json_encode($value) : (is_scalar($value) ? (string) $value : ''));
        }

        return $this->hasProblems($report) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{workflows: list<array<string, mixed>>}  $report
     */
    private function hasProblems(array $report): bool
    {
        foreach ($report['workflows'] as $workflow) {
            if (($workflow['problems'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }
}
