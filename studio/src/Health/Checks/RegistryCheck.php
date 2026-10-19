<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryManager;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Throwable;

/**
 * The registry index loads (every discovered definition is valid) and its cache is fresh.
 */
final readonly class RegistryCheck implements StudioHealthCheck
{
    public function __construct(private RegistryInterface $registry, private RegistryManager $manager) {}

    public function id(): string
    {
        return 'registry.index';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        try {
            $count = $this->registry->count();
            $failures = $this->registry instanceof Registry ? count($this->registry->failures()) : 0;
            $status = $this->manager->status();
        } catch (Throwable $exception) {
            return HealthCheckResult::failure($this->id(), 'Registry', mb_substr($exception->getMessage(), 0, 500), 'Fix the definition named in the message; gcego:registry-cache rebuilds the index.', 'gcego/registry');
        }

        if ($failures > 0) {
            return HealthCheckResult::warning($this->id(), 'Registry', sprintf('%d definitions, %d skipped as invalid (gcego-registry.strict is off).', $count, $failures), 'Fix or remove the invalid definitions.', 'gcego/registry');
        }

        if ($status->enabled && $status->exists && $status->fresh === false) {
            return HealthCheckResult::warning($this->id(), 'Registry', sprintf('%d definitions; the registry cache is stale.', $count), 'Run php artisan gcego:registry-cache.', 'gcego/registry');
        }

        return HealthCheckResult::pass($this->id(), 'Registry', sprintf('%d definitions loaded%s.', $count, $status->enabled && $status->exists && $status->valid ? ' (cached)' : ''), 'gcego/registry', ['definitions' => $count]);
    }
}
