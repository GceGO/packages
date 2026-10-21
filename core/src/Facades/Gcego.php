<?php

declare(strict_types=1);

namespace Gcego\Core\Facades;

use Gcego\Core\Bootstrap\BootstrapPlan;
use Gcego\Core\Bootstrap\BootstrapResult;
use Gcego\Core\Bootstrap\StartupTiming;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Extensions\ExtensionDescriptor;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Support\FrameworkVersion;
use Illuminate\Support\Facades\Facade;

/**
 * Static access to the GCEGO Studio framework, auto-aliased as \Gcego.
 *
 * Resolves FrameworkInterface from the container, so it serves whichever
 * implementation the application binds and can be replaced in tests with
 * Gcego::swap() or Gcego::shouldReceive().
 *
 * @method static string name()
 * @method static FrameworkVersion version()
 * @method static GcegoConfig config()
 * @method static FrameworkState state()
 * @method static bool isEnabled()
 * @method static bool isBooted()
 * @method static BootstrapResult boot()
 * @method static BootstrapResult|null bootResult()
 * @method static list<PackageDescriptor> packages()
 * @method static bool hasPackage(string $id)
 * @method static PackageDescriptor package(string $id)
 * @method static bool isPackageActive(string $id)
 * @method static list<ExtensionDescriptor> extensions()
 * @method static bool hasExtension(string $id)
 * @method static ExtensionDescriptor extension(string $id)
 * @method static bool isExtensionActive(string $id)
 * @method static bool hasCapability(string $capability)
 * @method static list<string> capabilities()
 * @method static BootstrapPlan plan()
 * @method static list<StartupTiming> timings()
 * @method static list<string> packageDependencies(string $id, bool $transitive = false, bool $includeOptional = false)
 * @method static list<string> packageDependants(string $id, bool $transitive = false, bool $includeOptional = false)
 * @method static array{packages: list<string>, extensions: list<string>} capabilityProviders(string $capability)
 * @method static DiagnosticReport diagnose()
 *
 * @see \Gcego\Core\Gcego
 * @see FrameworkInterface
 */
final class Gcego extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FrameworkInterface::class;
    }

    /**
     * Mock the contract: the framework implementation is final.
     */
    protected static function getMockableClass(): string
    {
        return FrameworkInterface::class;
    }
}
