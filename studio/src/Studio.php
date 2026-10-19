<?php

declare(strict_types=1);

namespace Gcego\Studio;

use Gcego\Modules\ModuleManager;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Studio\Bootstrap\BootstrapResult;
use Gcego\Studio\Compatibility\CompatibilityReport;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Contracts\StudioInterface;
use Gcego\Studio\Health\HealthReport;
use Illuminate\Contracts\Container\Container;

/**
 * GCEGO Admin Studio — the one entry point of the assembled framework:
 *
 *     $studio = app(Studio::class);
 *     $studio->panels();                 // ['admin', 'school']
 *     $studio->health()->healthy();      // the development checks
 *     $studio->health(production: true); // plus production readiness
 *     $studio->hasCapability('notifications');
 *     $studio->extend(MyStudioExtension::class);   // while booting
 *
 * Panels, modules and pages come from their own managers (gcego/panels,
 * gcego/modules, gcego/pages via the registry); Studio never keeps
 * request state and never queries the database on construction.
 */
final readonly class Studio implements StudioInterface
{
    public const string PackageName = 'gcego/studio';

    public const string Version = '0.1.0';

    public function __construct(private StudioManager $manager, private Container $container) {}

    public static function version(): string
    {
        return self::Version;
    }

    public function configuration(): StudioConfiguration
    {
        return $this->container->make(StudioConfiguration::class);
    }

    public function panels(): array
    {
        return $this->container->make(PanelManager::class)->names();
    }

    public function modules(): array
    {
        return $this->container->make(ModuleManager::class)->names();
    }

    public function pages(): array
    {
        return array_map(static fn ($descriptor): string => $descriptor->name(), $this->container->make(RegistryInterface::class)->descriptors('page'));
    }

    public function packages(): array
    {
        return $this->manager->packages()->packages();
    }

    /**
     * Capabilities of the registered GCEGO packages ("notifications", "media.uploads", "workflows"...).
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach ($this->manager->kernel()->packages() as $descriptor) {
            if ($this->manager->kernel()->isPackageActive($descriptor->id)) {
                $capabilities = [...$capabilities, ...$descriptor->capabilities];
            }
        }

        $capabilities = array_values(array_unique($capabilities));
        sort($capabilities);

        return $capabilities;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function health(bool $production = false): HealthReport
    {
        return $this->manager->health()->run($production);
    }

    public function compatibility(): CompatibilityReport
    {
        return $this->manager->compatibility()->check();
    }

    public function bootstrap(): BootstrapResult
    {
        return $this->manager->bootstrapper()->verify();
    }

    public function inspect(): array
    {
        return $this->manager->inspector()->report();
    }

    public function extend(string|StudioExtension $extension): void
    {
        $this->manager->extensions()->add($extension);
    }

    public function manager(): StudioManager
    {
        return $this->manager;
    }
}
