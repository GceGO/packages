<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Gcego\Studio\Integration\PackageIntegrationManager;

/**
 * The eighteen foundation packages are installed, discovered, registered and booted.
 */
final readonly class PackagesCheck implements StudioHealthCheck
{
    public function __construct(private PackageIntegrationManager $packages) {}

    public function id(): string
    {
        return 'packages.integrated';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $problems = $this->packages->problems();
        $optional = array_values(array_map(static fn ($status): string => $status->id, array_filter($this->packages->packages(), static fn ($status): bool => ! $status->required && $status->integrated())));

        return $problems === []
            ? HealthCheckResult::pass($this->id(), 'GCEGO packages', sprintf('All %d foundation packages are integrated%s.', count(PackageIntegrationManager::Required), $optional === [] ? '' : '; optional: '.implode(', ', $optional)), 'gcego/core', ['optional' => $optional])
            : HealthCheckResult::failure($this->id(), 'GCEGO packages', implode(' ', $problems), 'Install the missing packages and keep Laravel package discovery enabled for them.', 'gcego/core');
    }
}
