<?php

declare(strict_types=1);

namespace Gcego\Luminaries;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Luminaries\Caching\IconManifest;
use Gcego\Luminaries\Console\CacheCommand;
use Gcego\Luminaries\Console\CheckCommand;
use Gcego\Luminaries\Console\ClearCommand;
use Gcego\Luminaries\Console\EnumsCommand;
use Gcego\Luminaries\Console\ListCommand;
use Gcego\Luminaries\Console\ProvidersCommand;
use Gcego\Luminaries\Console\SearchCommand;
use Gcego\Luminaries\Http\ExplorerController;
use Gcego\Luminaries\Providers\BladeIconSetProvider;
use Gcego\Luminaries\Providers\CustomSvgProvider;
use Gcego\Luminaries\Providers\InlinePathProvider;
use Gcego\Luminaries\Registry\IconRegistry;
use Gcego\Luminaries\Rendering\IconRenderer;
use Gcego\Luminaries\Security\PathGuard;
use Gcego\Luminaries\Security\TrustedSvgValidator;
use Gcego\Luminaries\Support\BladeIconSets;
use Gcego\Luminaries\View\Components\Icon;
use Gcego\Luminaries\View\Components\IconButton;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Laravel integration for gcego/luminaries.
 *
 * Everything is a lazily built singleton: the registry (providers, custom
 * collections, presets and aliases) is assembled the first time an icon is
 * needed, not on every boot. Blade Icons' per-icon components are disabled
 * unless luminaries.blade_icons_components is true.
 */
final class LuminariesServiceProvider extends ServiceProvider
{
    public const string Views = 'luminaries';

    /**
     * Blade Icons and the icon sets Luminaries is built on. Registered here as
     * well as through package discovery, so Luminaries works when only GCEGO
     * providers are listed (registration is idempotent).
     */
    public const array IconProviders = [
        BladeIconsServiceProvider::class,
        BladeHeroiconsServiceProvider::class,
        BladeLucideIconsServiceProvider::class,
    ];

    public function register(): void
    {
        foreach (self::IconProviders as $provider) {
            $this->app->register($provider);
        }

        $this->mergeConfigFrom(self::path('config/luminaries.php'), 'luminaries');

        $this->app->singleton(LuminariesConfig::class, static function (Application $app): LuminariesConfig {
            $config = $app->make(Repository::class)->get('luminaries', []);

            return LuminariesConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(TrustedSvgValidator::class);
        $this->app->singleton(BladeIconSets::class);
        $this->app->singleton(InlinePathProvider::class);
        $this->app->singleton(PathGuard::class, static fn (Application $app): PathGuard => new PathGuard($app->make(LuminariesConfig::class)->trustedRoots));
        $this->app->singleton(IconRenderer::class, static fn (Application $app): IconRenderer => new IconRenderer($app->make(LuminariesConfig::class)));
        $this->app->singleton(IconManifest::class, static function (Application $app): IconManifest {
            $config = $app->make(LuminariesConfig::class);

            return new IconManifest($config->cachePath, $config->fingerprint());
        });
        $this->app->singleton(IconRegistry::class, static fn (Application $app): IconRegistry => self::registry($app));
        $this->app->singleton(Luminaries::class, static fn (Application $app): Luminaries => new Luminaries(
            $app->make(IconRegistry::class),
            $app->make(IconRenderer::class),
            $app->make(LuminariesConfig::class),
            $app->make(IconManifest::class),
            $app->make(InlinePathProvider::class),
            static fn (): ?LoggerInterface => $app->bound('log') ? $app->make('log') : null,
        ));

        if (! (bool) $this->app->make(Repository::class)->get('luminaries.blade_icons_components', false)) {
            $this->app->make(Repository::class)->set('blade-icons.components.disabled', true);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::path('resources/views'), self::Views);

        Blade::component('gcego-icon', Icon::class);
        Blade::component('gcego-icon-button', IconButton::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ProvidersCommand::class, ListCommand::class, SearchCommand::class, CheckCommand::class, CacheCommand::class, ClearCommand::class, EnumsCommand::class]);
            $this->optimizes('gcego:icons:cache', 'gcego:icons:clear', 'luminaries');
            $this->publishes([self::path('config/luminaries.php') => $this->app->configPath('luminaries.php')], 'luminaries-config');
        }

        if ((bool) $this->app->make(Repository::class)->get('luminaries.explorer.enabled', false)) {
            if (! Gate::has(ExplorerController::Ability)) {
                Gate::define(ExplorerController::Ability, fn (?Authenticatable $user = null): bool => $this->app->environment('local'));
            }

            $this->loadRoutesFrom(self::path('routes/explorer.php'));
        }
    }

    public static function path(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : '/'.$path);
    }

    private static function registry(Application $app): IconRegistry
    {
        $config = $app->make(LuminariesConfig::class);
        $registry = new IconRegistry;
        $sets = $app->make(BladeIconSets::class);

        foreach ($config->providers as $name => $provider) {
            $registry->addProvider(new BladeIconSetProvider($sets, $name, $provider['label'], $provider['set'], $provider['variant'], $provider['style'], $provider['stroke']));
        }

        foreach ($config->collections as $name => $collection) {
            $registry->addProvider(new CustomSvgProvider($name, $collection['label'], $collection['path'], $app->make(PathGuard::class), $app->make(TrustedSvgValidator::class)));
        }

        $registry->addProvider($app->make(InlinePathProvider::class));

        foreach ($config->allAliases() as $alias => $definition) {
            $registry->alias($alias, $definition['target'], override: true, origin: $definition['origin']);
        }

        return $registry;
    }
}
