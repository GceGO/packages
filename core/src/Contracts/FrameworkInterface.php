<?php

declare(strict_types=1);

namespace Gcego\Core\Contracts;

use Gcego\Core\Bootstrap\BootstrapPlan;
use Gcego\Core\Bootstrap\BootstrapResult;
use Gcego\Core\Bootstrap\StartupTiming;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Support\FrameworkVersion;

/**
 * The public face of the GCEGO framework, resolvable from the container.
 */
interface FrameworkInterface
{
    public function name(): string;

    public function version(): FrameworkVersion;

    public function config(): GcegoConfig;

    public function state(): FrameworkState;

    public function isEnabled(): bool;

    public function isBooted(): bool;

    public function boot(): BootstrapResult;

    public function bootResult(): ?BootstrapResult;

    /**
     * @return list<PackageDescriptor> Registered packages, in registration order.
     */
    public function packages(): array;

    public function hasPackage(string $id): bool;

    public function package(string $id): PackageDescriptor;

    public function isPackageActive(string $id): bool;

    /**
     * @return list<ExtensionDescriptor> Registered extensions, in registration order.
     */
    public function extensions(): array;

    public function hasExtension(string $id): bool;

    public function extension(string $id): ExtensionDescriptor;

    public function isExtensionActive(string $id): bool;

    /**
     * Whether an active package or extension declares the capability.
     */
    public function hasCapability(string $capability): bool;

    /**
     * @return list<string> Unique active capabilities, sorted by name.
     */
    public function capabilities(): array;

    public function plan(): BootstrapPlan;

    /** @return list<StartupTiming> */
    public function timings(): array;

    /** @return list<string> */
    public function packageDependencies(string $id, bool $transitive = false, bool $includeOptional = false): array;

    /** @return list<string> */
    public function packageDependants(string $id, bool $transitive = false, bool $includeOptional = false): array;

    /** @return array{packages: list<string>, extensions: list<string>} */
    public function capabilityProviders(string $capability): array;

    public function diagnose(): DiagnosticReport;
}
