<?php

declare(strict_types=1);

namespace Gcego\Pages;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\Actions\PageActionResolver;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Breadcrumbs\PageBreadcrumbs;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Diagnostics\PageInspector;
use Gcego\Pages\Lifecycle\PageHooks;
use Gcego\Pages\Lifecycle\PageLifecycle;
use Gcego\Pages\Livewire\PageHost;
use Gcego\Pages\Navigation\PageNavigationContributor;
use Gcego\Pages\Records\PageRecordResolver;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Registry\PageKind;
use Gcego\Pages\Rendering\ContentRenderer;
use Gcego\Pages\Rendering\PageLinkRenderer;
use Gcego\Pages\Rendering\PageRenderContext;
use Gcego\Pages\Rendering\PageRenderer;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Runtime\Rendering\RendererRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/pages; also the "gcego/pages" GCEGO package.
 *
 * Registers the "page" registry kind (in register(), so discovery sees it),
 * the runtime renderers, the PageHost Livewire component, views,
 * translations and styling slots. Page routes are registered once the
 * application has booted (after the application's own routes, so conflicts
 * are detected), from the registry index only, and never when routes are
 * cached. Booting never queries, opens a page or loads a page definition.
 */
final class PagesServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-pages';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-pages.php', 'gcego-pages');

        $this->app->singleton(PagesConfig::class, static function (Application $app): PagesConfig {
            $config = $app->make(Repository::class)->get('gcego-pages', []);

            return PagesConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([PageManager::class, PageDefinitions::class, PageRouteResolver::class, PageRouteRegistrar::class, PageHooks::class, PageRenderContext::class, PageInspector::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request: they hold the current user's decisions and open pages.
        foreach ([PageAuthorizer::class, PageRecordResolver::class, PageBreadcrumbs::class, PageActionResolver::class, PageLifecycle::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new PageKind));
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        $this->publishes([$root.'/config/gcego-pages.php' => $this->app->configPath('gcego-pages.php')], 'gcego-pages-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-pages-views');

        $config = $this->app->make(PagesConfig::class);
        $renderers = $this->app->make(RendererRegistry::class);
        $renderers->register(Page::class, PageRenderer::class);
        $renderers->register(Content::class, ContentRenderer::class);
        $renderers->register(PageLink::class, PageLinkRenderer::class);
        Livewire::component('gcego-page', PageHost::class);

        $this->defineSlots();

        if ($config->navigationAutoRegister) {
            $this->app->make(NavigationManager::class)->contribute(PageNavigationContributor::class);
        }

        if ($config->routing && ! ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            $this->app->booted(function (): void {
                $this->app->make(PageRouteRegistrar::class)->register($this->app->make(Router::class));
            });
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/pages',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/layouts', 'gcego/navigation'],
            capabilities: ['pages', 'pages.routing', 'pages.records', 'pages.livewire', 'pages.navigation'],
            description: 'Declarative pages: index, create, edit, view, dashboard and custom pages with routes, lifecycle, authorization, headers, breadcrumbs and one Livewire host.',
        );
    }

    private function defineSlots(): void
    {
        $styling = $this->app->make(ChromaManager::class);
        $slots = static function (array $slots): array {
            $definitions = [];

            foreach ($slots as $slot => $description) {
                $definitions[] = new SlotDefinition((string) $slot, description: is_string($description) ? $description : null);
            }

            return $definitions;
        };

        $styling->defineSlots(Page::class, $slots(Page::styleSlots()), inherit: true);
        $styling->defineSlots(Content::class, $slots(Content::styleSlots()), inherit: false);
        $styling->defineSlots(PageLink::class, $slots(PageLink::styleSlots()), inherit: false);
    }
}
