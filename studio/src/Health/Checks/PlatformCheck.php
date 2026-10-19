<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Compatibility\PackageCompatibilityChecker;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;

/**
 * PHP, Laravel and Livewire, and every installed GCEGO package's own constraints.
 */
final readonly class PlatformCheck implements StudioHealthCheck
{
    public function __construct(private PackageCompatibilityChecker $compatibility) {}

    public function id(): string
    {
        return 'platform.compatibility';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $report = $this->compatibility->check();
        $versions = sprintf('PHP %s, Laravel %s, Livewire %s', $report->versions['php'] ?? '?', $report->versions['laravel/framework'] ?? '?', $report->versions['livewire/livewire'] ?? 'not installed');

        if (! $report->compatible()) {
            return HealthCheckResult::failure($this->id(), 'Platform and package compatibility', implode(' ', $report->problems), 'Install compatible versions (composer update), then run this check again.', 'gcego/studio', ['versions' => $versions]);
        }

        if ($report->notices !== []) {
            return HealthCheckResult::warning($this->id(), 'Platform and package compatibility', $versions.'. '.implode(' ', $report->notices), 'Install composer/semver to verify package constraints.', 'gcego/studio');
        }

        return HealthCheckResult::pass($this->id(), 'Platform and package compatibility', $versions.'; every GCEGO package constraint is satisfied.', 'gcego/studio');
    }
}
