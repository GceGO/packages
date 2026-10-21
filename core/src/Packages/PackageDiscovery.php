<?php

declare(strict_types=1);

namespace Gcego\Core\Packages;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Diagnostics\InfrastructureIssues;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/** Loaded providers only: no filesystem scans or deferred-provider loading. */
final readonly class PackageDiscovery
{
    public function __construct(private Application $app, private PackageManager $packages, private InfrastructureIssues $issues = new InfrastructureIssues) {}

    public function discover(): void
    {
        try {
            $this->registerProviders($this->packages);
        } catch (Throwable $exception) {
            $this->issues->record('packages.discovery.failed', exception: $exception);
            throw $exception;
        }
    }

    public function snapshot(): PackageManager
    {
        $snapshot = $this->packages->snapshot(preserveLock: true);

        $this->registerProviders($snapshot);

        return $snapshot;
    }

    private function registerProviders(PackageManager $target): void
    {
        foreach ($this->app->getProviders(PackageInterface::class) as $provider) {
            if ($provider instanceof PackageInterface) {
                $target->register($provider);
            }
        }
    }
}
