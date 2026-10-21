<?php

declare(strict_types=1);

namespace Gcego\Core\Console;

use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Gcego;
use Illuminate\Console\Command;

/**
 * Read-only diagnostics for operators and CI. Never boots packages itself.
 */
final class DiagnoseCommand extends Command
{
    protected $signature = 'gcego:diagnose {--json : Output a JSON report} {--strict : Fail on warnings as well as errors}';

    protected $description = 'Check GCEGO configuration, startup, packages and extensions';

    public function handle(Gcego $gcego): int
    {
        $report = $gcego->diagnose();

        if ($this->option('json')) {
            $this->line(json_encode($report->toStructuredArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } else {
            $this->table(['Code', 'Severity', 'Message', 'Resolution'], array_map(
                static fn (DiagnosticResult $result): array => [$result->check, $result->severity(), $result->message, $result->recommendedResolution() ?? ''],
                $report->results(),
            ));
        }

        return $report->hasFailures() || ($this->option('strict') && $report->hasWarnings())
            ? self::FAILURE
            : self::SUCCESS;
    }
}
