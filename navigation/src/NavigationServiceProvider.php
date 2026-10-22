<?php

declare(strict_types=1);

namespace Gcego\Navigation;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Tailwind;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Navigation\Badges\BadgeResolver;
use Gcego\Navigation\Breadcrumbs\BreadcrumbResolver;
use Gcego\Navigation\Components\Divider;
use Gcego\Navigation\Components\Dropdown;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Heading;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Diagnostics\NavigationInspector;
use Gcego\Navigation\Registry\NavigationKind;
use Gcego\Navigation\Rendering\NavigationRenderer;
use Gcego\Navigation\Resolution\NavigationResolver;
use Gcego\Navigation\Resolution\RouteResolver;
use Gcego\Navigation\Resolution\VisibilityResolver;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Security\Authorization\AuthorizationEngine;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/navigation; also the "gcego/navigation" GCEGO package.
 *
 * Registers the "navigation" registry kind (in register(), so discovery
 * sees it), the runtime renderer, request-scoped resolvers, views,
 * translations, the <x-gcego-navigation::navigation> and ::breadcrumbs
 * components, styling slots and a few navigation icons. Booting never
 * queries, resolves routes or loads navigation definitions.
 */
final class NavigationServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-navigation';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-navigation.php', 'gcego-navigation');

        $this->app->singleton(NavigationConfig::class, static function (Application $app): NavigationConfig {
            $config = $app->make(Repository::class)->get('gcego-navigation', []);

            return NavigationConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(NavigationManager::class);
        $this->app->singleton(NavigationInspector::class);
        $this->app->singleton(BreadcrumbResolver::class);
        $this->app->singleton(BadgeResolver::class, static fn (Application $app): BadgeResolver => new BadgeResolver($app, $app->make(CacheFactory::class), $app->make(NavigationConfig::class)->badgeStore));
        $this->app->scoped(VisibilityResolver::class, static fn (Application $app): VisibilityResolver => new VisibilityResolver($app->make(AuthorizationEngine::class), $app->make(NavigationConfig::class)->defaultVisibility));
        $this->app->scoped(RouteResolver::class, static fn (Application $app): RouteResolver => new RouteResolver($app->make(Router::class), $app->make(UrlGenerator::class), $app->make(NavigationConfig::class)->absoluteUrls));
        $this->app->scoped(NavigationResolver::class);

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new NavigationKind));
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        Blade::anonymousComponentPath($root.'/resources/views/components', self::Views);
        $this->publishes([$root.'/config/gcego-navigation.php' => $this->app->configPath('gcego-navigation.php')], 'gcego-navigation-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-navigation-views');

        $this->app->make(NavigationConfig::class);
        $this->app->make(RendererRegistry::class)->register(Navigation::class, NavigationRenderer::class);

        $styling = $this->app->make(ChromaManager::class);
        $slots = static function (array $slots): array {
            $definitions = [];

            foreach ($slots as $slot => $description) {
                $definitions[] = new SlotDefinition((string) $slot, description: is_string($description) ? $description : null);
            }

            return $definitions;
        };
        $styling->defineSlots(Navigation::class, $slots(Navigation::styleSlots()), inherit: false);
        $link = [Navigation::ItemSlot => 'The link.', Tailwind::Icon => 'Its icon.', Tailwind::Label => 'Its label.', Navigation::BadgeSlot => 'Its badge.'];

        foreach ([Item::class, ExternalLink::class] as $class) {
            $styling->defineSlots($class, $slots($link), inherit: false);
        }

        foreach ([Group::class, Dropdown::class] as $class) {
            $styling->defineSlots($class, $slots([Navigation::GroupSlot => 'The group.', Navigation::GroupHeaderSlot => 'Its header or toggle.', Navigation::GroupItemsSlot => 'Its list.', Navigation::DropdownSlot => 'Its dropdown panel (top navigation).', Navigation::ItemSlot => 'Its toggle in the top navigation.', Tailwind::Icon => 'Its icon.']), inherit: false);
        }

        $styling->defineSlots(Heading::class, $slots([Navigation::HeadingSlot => 'The heading.']), inherit: false);
        $styling->defineSlots(Divider::class, $slots([Navigation::DividerSlot => 'The divider.']), inherit: false);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/navigation',
            requires: ['gcego/core', 'gcego/registry', 'gcego/rendering', 'gcego/chroma', 'gcego/security', 'gcego/data', 'gcego/tables'],
            optional: ['gcego/layouts'],
            capabilities: ['navigation', 'navigation.breadcrumbs', 'navigation.badges', 'navigation.contributions'],
            description: 'Declarative navigation: sidebar, top and mobile menus, breadcrumbs, active states, badges and permission-aware visibility.',
        );
    }
}
