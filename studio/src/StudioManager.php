<?php

declare(strict_types=1);

namespace Gcego\Studio;

use Gcego\Core\Gcego;
use Gcego\Studio\Assets\StudioAssetManager;
use Gcego\Studio\Bootstrap\StudioBootstrapper;
use Gcego\Studio\Compatibility\PackageCompatibilityChecker;
use Gcego\Studio\Diagnostics\StudioInspector;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Health\StudioHealthManager;
use Gcego\Studio\Installation\InstallationManager;
use Gcego\Studio\Integration\FrameworkCatalog;
use Gcego\Studio\Integration\PackageIntegrationManager;
use Illuminate\Contracts\Container\Container;

/**
 * Hands out Studio's services, resolved lazily from the container (none
 * is built until it is asked for). It coordinates; each service owns its
 * work, and the packages own theirs (gcego/core boots, gcego/panels routes).
 */
final readonly class StudioManager
{
    public function __construct(private Container $container) {}

    public function kernel(): Gcego
    {
        return $this->container->make(Gcego::class);
    }

    public function extensions(): StudioExtensionRegistry
    {
        return $this->container->make(StudioExtensionRegistry::class);
    }

    public function bootstrapper(): StudioBootstrapper
    {
        return $this->container->make(StudioBootstrapper::class);
    }

    public function packages(): PackageIntegrationManager
    {
        return $this->container->make(PackageIntegrationManager::class);
    }

    public function compatibility(): PackageCompatibilityChecker
    {
        return $this->container->make(PackageCompatibilityChecker::class);
    }

    public function catalog(): FrameworkCatalog
    {
        return $this->container->make(FrameworkCatalog::class);
    }

    public function health(): StudioHealthManager
    {
        return $this->container->make(StudioHealthManager::class);
    }

    public function assets(): StudioAssetManager
    {
        return $this->container->make(StudioAssetManager::class);
    }

    public function installer(): InstallationManager
    {
        return $this->container->make(InstallationManager::class);
    }

    public function inspector(): StudioInspector
    {
        return $this->container->make(StudioInspector::class);
    }
}
