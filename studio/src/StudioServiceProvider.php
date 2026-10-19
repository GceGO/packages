<?php

declare(strict_types=1);

namespace Gcego\Studio;

use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Panels\PanelsConfig;
use Gcego\Registry\RegistryConfig;
use Gcego\Studio\Bootstrap\StudioBootstrapper;
use Gcego\Studio\Configuration\StudioConfiguration;
use Gcego\Studio\Console\HealthCommand;
use Gcego\Studio\Console\InspectCommand;
use Gcego\Studio\Console\InstallCommand;
use Gcego\Studio\Defaults\StudioDefaults;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/studio; also the "gcego/studio" GCEGO package.
 *
 * Composer and Laravel package discovery load the eighteen foundation
 * providers; gcego/core registers and boots them. Studio adds:
 *
 * register(): its configuration, services (singletons that hold no request
 * state), the appearance and discovery defaults (applied when the panels'
 * and registry's configurations are first resolved), and — so it runs
 * before gcego/panels builds its routes — the application of trusted
 * extensions once the application has booted. No queries.
 * boot(): publishing (config, stubs) and the console commands
 * gcego:install, gcego:studio:health and gcego:studio:inspect.
 */
final class StudioServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-studio';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-studio.php', self::Name);

        $this->app->singleton(StudioConfiguration::class, static function (Application $app): StudioConfiguration {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return StudioConfiguration::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(StudioExtensionRegistry::class);
        $this->app->singleton(StudioBootstrapper::class);
        $this->app->singleton(StudioManager::class);
        $this->app->singleton(Studio::class);
        $this->app->alias(Studio::class, Contracts\StudioInterface::class);

        $this->app->beforeResolving(PanelsConfig::class, fn (): null => $this->defaults(static fn (StudioDefaults $defaults, StudioConfiguration $studio) => $defaults->applyToPanels($studio)));
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->defaults(static fn (StudioDefaults $defaults, StudioConfiguration $studio) => $defaults->applyToDiscovery($studio)));

        // Registered now (not in boot) so it runs before gcego/panels registers its routes.
        $this->app->booted(function (Application $app): void {
            $studio = $app->make(StudioConfiguration::class);

            if (! $studio->enabled) {
                return;
            }

            $extensions = $app->make(StudioExtensionRegistry::class);

            foreach ($studio->extensions as $extension) {
                $extensions->add($extension);
            }

            $app->make(StudioBootstrapper::class)->apply();
        });
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);

        if ($this->app->runningInConsole()) {
            $this->publishes([$root.'/config/gcego-studio.php' => $this->app->configPath('gcego-studio.php')], 'gcego-studio-config');
            $this->publishes([$root.'/stubs' => $this->app->basePath('stubs/gcego-studio')], 'gcego-studio-stubs');
            $this->commands([InstallCommand::class, HealthCommand::class, InspectCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            Studio::PackageName,
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/layouts', 'gcego/navigation', 'gcego/pages', 'gcego/modules', 'gcego/panels'],
            capabilities: ['studio', 'studio.install', 'studio.health', 'studio.extensions'],
            description: 'GCEGO Admin Studio: framework assembly, installation, health and diagnostics.',
        );
    }

    /**
     * @param  \Closure(StudioDefaults, StudioConfiguration): void  $apply
     */
    private function defaults(\Closure $apply): null
    {
        $studio = $this->app->make(StudioConfiguration::class);

        if ($studio->enabled) {
            $apply($this->app->make(StudioDefaults::class), $studio);
        }

        return null;
    }
}
