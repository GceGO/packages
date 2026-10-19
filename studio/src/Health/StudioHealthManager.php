<?php

declare(strict_types=1);

namespace Gcego\Studio\Health;

use Gcego\Studio\Assets\StudioAssetManager;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Health\Checks\AssetsCheck;
use Gcego\Studio\Health\Checks\AuthenticationCheck;
use Gcego\Studio\Health\Checks\ConfigurationCheck;
use Gcego\Studio\Health\Checks\CoreCheck;
use Gcego\Studio\Health\Checks\PackagesCheck;
use Gcego\Studio\Health\Checks\PanelAccessCheck;
use Gcego\Studio\Health\Checks\PlatformCheck;
use Gcego\Studio\Health\Checks\ProductionCheck;
use Gcego\Studio\Health\Checks\RegistryCheck;
use Gcego\Studio\Health\Checks\RoutesCheck;
use Gcego\Studio\Health\Checks\StorageCheck;
use Gcego\Studio\Health\Checks\TenancyCheck;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Runs the health checks, read only: the built-in ones, then those
 * Studio extensions contribute. A check that throws is reported as a
 * failure (its message, never a stack trace); results are never cached,
 * so long-running workers always see the current state.
 */
final readonly class StudioHealthManager
{
    /** Built-in development checks, in order. */
    public const array Checks = [
        PlatformCheck::class, PackagesCheck::class, CoreCheck::class, ConfigurationCheck::class, RegistryCheck::class,
        RoutesCheck::class, AuthenticationCheck::class, PanelAccessCheck::class, TenancyCheck::class, AssetsCheck::class, StorageCheck::class,
    ];

    public function __construct(private Container $container, private StudioExtensionRegistry $extensions) {}

    /**
     * @param  bool  $production  Add the production readiness checks.
     */
    public function run(bool $production = false): HealthReport
    {
        $results = [];

        foreach ($this->checks($production) as $check) {
            $results[] = $this->safely($check);
        }

        return new HealthReport($results);
    }

    /**
     * @return list<StudioHealthCheck|string>
     */
    public function checks(bool $production = false): array
    {
        $checks = self::Checks;

        if ($production) {
            foreach (ProductionCheck::Rules as $rule) {
                $checks[] = new ProductionCheck($rule, $this->container->make(Application::class), $this->container->make(Repository::class));
            }

            $checks[] = new AssetsCheck($this->container->make(StudioAssetManager::class), true);
        }

        foreach ($this->extensions->contexts() as $context) {
            foreach ($context->checks() as $check) {
                $checks[] = $check;
            }
        }

        return $checks;
    }

    private function safely(StudioHealthCheck|string $check): HealthCheckResult
    {
        $id = is_string($check) ? $check : $check->id();

        try {
            $instance = is_string($check) ? $this->container->make($check) : $check;

            if (! $instance instanceof StudioHealthCheck) {
                return HealthCheckResult::failure($id, $id, 'Not a health check.', 'Register classes implementing StudioHealthCheck.');
            }

            $id = $instance->id();

            return $instance->run();
        } catch (Throwable $exception) {
            return HealthCheckResult::failure($id, $id, 'The check could not run: '.mb_substr($exception->getMessage(), 0, 300), 'Fix the error above; the check itself never changes anything.');
        }
    }
}
