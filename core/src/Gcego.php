<?php

declare(strict_types=1);

namespace Gcego\Core;

use Gcego\Core\Bootstrap\BootstrapResult;
use Gcego\Core\Bootstrap\FrameworkBootstrapper;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Diagnostics\FrameworkDiagnostics;
use Gcego\Core\Exceptions\PackageRegistrationException;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\FrameworkVersion;
use Illuminate\Contracts\Container\Container;

/**
 * The framework entry point: app(Gcego::class) or constructor injection.
 *
 * Gcego holds no state of its own. It is a read-mostly facade over the
 * container-managed services (lifecycle, packages, extensions, configuration)
 * that own it, so there is exactly one source of truth for each concern. This
 * class also takes the role of the "framework manager"; a separate
 * GcegoManager would only duplicate it.
 */
final readonly class Gcego implements FrameworkInterface
{
    public const string PackageName = 'gcego/core';

    public function __construct(
        private Container $container,
        private LifecycleManager $lifecycle,
        private PackageManager $packageManager,
        private ExtensionManager $extensionManager,
        private FrameworkBootstrapper $bootstrapper,
        private FrameworkVersion $frameworkVersion,
    ) {}

    public function name(): string
    {
        return $this->config()->name();
    }

    public function version(): FrameworkVersion
    {
        return $this->frameworkVersion;
    }

    /**
     * Resolved lazily so state(), diagnose() and other read-only calls work
     * even with invalid configuration; this call throws ConfigurationException
     * when config('gcego') is invalid.
     */
    public function config(): GcegoConfig
    {
        return $this->container->make(GcegoConfig::class);
    }

    public function state(): FrameworkState
    {
        return $this->lifecycle->state();
    }

    public function isEnabled(): bool
    {
        return $this->config()->enabled();
    }

    public function isBooted(): bool
    {
        return $this->lifecycle->isBooted();
    }

    /**
     * Boot now (idempotent). Only needed when gcego.auto_boot is false.
     */
    public function boot(): BootstrapResult
    {
        return $this->bootstrapper->boot();
    }

    public function bootResult(): ?BootstrapResult
    {
        return $this->bootstrapper->result();
    }

    public function packages(): array
    {
        return $this->packageManager->descriptors();
    }

    public function hasPackage(string $id): bool
    {
        return $this->packageManager->has($id);
    }

    /**
     * @throws PackageRegistrationException
     */
    public function package(string $id): PackageDescriptor
    {
        return $this->packageManager->descriptor($id);
    }

    public function isPackageActive(string $id): bool
    {
        return $this->packageManager->isBooted($id);
    }

    /**
     * @return list<ExtensionDescriptor>
     */
    public function extensions(): array
    {
        return $this->extensionManager->descriptors();
    }

    public function hasExtension(string $id): bool
    {
        return $this->extensionManager->has($id);
    }

    public function isExtensionActive(string $id): bool
    {
        return $this->extensionManager->isActive($id);
    }

    public function hasCapability(string $capability): bool
    {
        foreach ($this->packageManager->booted() as $id) {
            if ($this->packageManager->descriptor($id)->hasCapability($capability)) {
                return true;
            }
        }

        foreach ($this->extensionManager->active() as $id) {
            if ($this->extensionManager->descriptor($id)->hasCapability($capability)) {
                return true;
            }
        }

        return false;
    }

    public function diagnose(): DiagnosticReport
    {
        return $this->container->make(FrameworkDiagnostics::class)->run();
    }
}
