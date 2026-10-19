<?php

declare(strict_types=1);

namespace Gcego\Panels;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\PagesConfig;
use Gcego\Panels\Access\PanelAccess;
use Gcego\Panels\Assets\PanelAssetResolver;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Diagnostics\PanelInspector;
use Gcego\Panels\Extensions\PanelExtensions;
use Gcego\Panels\Http\Middleware\AuthorizePanel;
use Gcego\Panels\Menu\UserMenu;
use Gcego\Panels\Navigation\PanelNavigationContributor;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelKind;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Panels\Shells\PanelShellFactory;
use Gcego\Panels\Switching\PanelSwitcher;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\RegistryConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/panels; also the "gcego/panels" GCEGO package.
 *
 * register(): configuration, services, the "panel" registry kind, the panel
 * discovery roots and, while panels route pages, gcego/pages' routing turned
 * off and its layout replaced by the panel shell (both decided lazily, just
 * before gcego/pages reads its configuration). boot(): views, translations,
 * the "gcego.panel" middleware (also Livewire persistent middleware),
 * styling slots, icons, the navigation contributor and, once the
 * application has booted, the panel routes (from the registry index; never
 * when routes are cached). Booting never queries or loads a panel.
 */
final class PanelsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-panels';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-panels.php', self::Name);

        $this->app->singleton(PanelsConfig::class, static function (Application $app): PanelsConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return PanelsConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([PanelManager::class, PanelDefinitions::class, PanelMembership::class, PanelRoutes::class, PanelRouteRegistrar::class, PanelInspector::class, PanelAssetResolver::class, PanelExtensions::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request: the verified panel context and what depends on the current user.
        foreach ([PanelContextResolver::class, PanelAccess::class, PanelSwitcher::class, UserMenu::class, PanelShellFactory::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new PanelKind));

        // Configuration is final by the time these are first resolved (Testbench sets it after register()).
        $this->app->beforeResolving(RegistryConfig::class, fn (): null => $this->addDiscoveryRoots());
        $this->app->beforeResolving(PagesConfig::class, fn (): null => $this->takeOverPages());
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->publishes([$root.'/config/gcego-panels.php' => $this->app->configPath('gcego-panels.php')], 'gcego-panels-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-panels')], 'gcego-panels-views');

        $this->app->make(Router::class)->aliasMiddleware(AuthorizePanel::Alias, AuthorizePanel::class);

        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware([AuthorizePanel::class]);
        }

        $this->defineSlots();

        if ($this->app->bound(NavigationManager::class)) {
            $this->app->make(NavigationManager::class)->contribute(PanelNavigationContributor::class);
        }

        if (! ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            $this->app->booted(function (): void {
                if ($this->app->make(PanelsConfig::class)->registerPageRoutes) {
                    $this->app->make(PanelRouteRegistrar::class)->register($this->app->make(Router::class));
                }
            });
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/panels',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/layouts', 'gcego/navigation', 'gcego/pages', 'gcego/modules'],
            capabilities: ['panels', 'panels.routing', 'panels.access', 'panels.tenancy', 'panels.navigation', 'panels.shells', 'panels.switching', 'panels.branding'],
            description: 'Multi-panel workspaces: routes, middleware, guards, access policies, tenancy, modules, pages, navigation and responsive shells per panel.',
        );
    }

    private function defineSlots(): void
    {
        $slots = [];

        foreach (Panel::styleSlots() as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(Panel::class, $slots, inherit: true);
    }

    /**
     * Panel folders become gcego/registry discovery roots of kind "panel" (when missing).
     */
    private function addDiscoveryRoots(): null
    {
        $config = $this->app->make(Repository::class);
        $paths = $config->get(self::Name.'.discovery.paths', []);
        $roots = $config->get('gcego-registry.discovery.roots', []);
        $roots = is_array($roots) ? $roots : [];

        foreach (is_array($paths) ? $paths : [] as $path) {
            foreach ($roots as $root) {
                if (! is_string($path) || (is_array($root) && ($root['kind'] ?? null) === PanelKind::Id && ($root['path'] ?? null) === $path)) {
                    continue 2;
                }
            }

            $roots[] = ['path' => $path, 'kind' => PanelKind::Id, 'entrypoint' => 'file'];
        }

        $config->set('gcego-registry.discovery.roots', $roots);

        return null;
    }

    /**
     * While panels route pages, gcego/pages registers no routes of its own
     * (a page is reachable only through the panels that include it) and
     * renders inside the panel shell, unless the application chose its own layout.
     */
    private function takeOverPages(): null
    {
        $config = $this->app->make(Repository::class);

        if ($config->get(self::Name.'.routing.register_page_routes', true) !== true) {
            return null;
        }

        $config->set('gcego-pages.routing.enabled', false);

        if (in_array($config->get('gcego-pages.rendering.layout'), [null, 'gcego-pages::layouts.app'], true)) {
            $config->set('gcego-pages.rendering.layout', 'gcego-panels::document');
        }

        return null;
    }
}
