<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\FrameworkDiagnostics;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;

/**
 * gcego/core's own diagnostics: configuration, registration, boot, package dependencies.
 */
final readonly class CoreCheck implements StudioHealthCheck
{
    public function __construct(private FrameworkDiagnostics $diagnostics) {}

    public function id(): string
    {
        return 'core.boot';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $report = $this->diagnostics->run();
        $describe = static fn (DiagnosticResult $result): string => $result->check.': '.$result->message;

        if ($report->hasFailures()) {
            return HealthCheckResult::failure($this->id(), 'Framework kernel', implode(' ', array_map($describe, $report->failures())), 'Fix the reported gcego/core problems (configuration in config/gcego.php, package dependencies).', 'gcego/core');
        }

        if ($report->hasWarnings()) {
            return HealthCheckResult::warning($this->id(), 'Framework kernel', implode(' ', array_map($describe, $report->warnings())), 'Review the reported gcego/core warnings.', 'gcego/core');
        }

        return HealthCheckResult::pass($this->id(), 'Framework kernel', sprintf('gcego/core is healthy (%d checks).', $report->count()), 'gcego/core');
    }
}
