<?php

declare(strict_types=1);

namespace Gcego\Core;

use Gcego\Core\Bootstrap\FrameworkBootstrapper;
use Gcego\Core\Configuration\ConfigurationValidator;
use Gcego\Core\Configuration\GcegoConfig;
use Gcego\Core\Contracts\FrameworkInterface;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Support\FrameworkVersion;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Integrates GCEGO with Laravel. Auto-discovered through composer.json.
 *
 * register() only merges configuration and declares bindings: nothing is
 * resolved except the tiny lifecycle tracker. boot() publishes the config and
 * schedules framework boot for after every provider has booted. No routes,
 * views, I/O or discovery scans happen here.
 *
 * The provider is itself the "gcego/core" package, so dependants can declare
 * requires: ['gcego/core'].
 */
final class GcegoServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string ConfigTag = 'gcego-config';

    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'gcego');

        $this->app->singleton(LifecycleManager::class);
        $this->app->singleton(PackageManager::class);
        $this->app->singleton(ExtensionManager::class);
        $this->app->singleton(FrameworkBootstrapper::class);
        $this->app->singleton(FrameworkVersion::class, static fn (): FrameworkVersion => FrameworkVersion::detect(Gcego::PackageName));
        $this->app->singleton(GcegoConfig::class, static function (Application $app): GcegoConfig {
            $config = $app->make(Repository::class)->get('gcego', []);

            return GcegoConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(Gcego::class);
        $this->app->alias(Gcego::class, FrameworkInterface::class);

        $this->app->make(LifecycleManager::class)->markRegistered();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::configPath() => $this->app->configPath('gcego.php')], self::ConfigTag);
        }

        $this->app->booted(static function (Application $app): void {
            if (self::shouldAutoBoot($app)) {
                $app->make(Gcego::class)->boot();
            }
        });
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            Gcego::PackageName,
            capabilities: ['framework'],
            description: 'GCEGO Admin Studio framework kernel.',
        );
    }

    /**
     * Read auto_boot leniently: invalid configuration elsewhere must not stop
     * the boot attempt, because boot() is what records the Failed state and
     * reports the configuration errors.
     */
    private static function shouldAutoBoot(Application $app): bool
    {
        $config = $app->make(Repository::class)->get('gcego', []);

        return $app->make(ConfigurationValidator::class)->validate(is_array($config) ? $config : [])['values']['auto_boot'];
    }

    public static function configPath(): string
    {
        return dirname(__DIR__).'/config/gcego.php';
    }
}
