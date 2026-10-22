<?php

declare(strict_types=1);

namespace Gcego\Layouts;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Layouts\Components\Accordion;
use Gcego\Layouts\Components\AccordionItem;
use Gcego\Layouts\Components\Actions;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Card;
use Gcego\Layouts\Components\Collapsible;
use Gcego\Layouts\Components\Container;
use Gcego\Layouts\Components\Divider;
use Gcego\Layouts\Components\EmptyState;
use Gcego\Layouts\Components\Footer;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Loading;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Row;
use Gcego\Layouts\Components\ScrollArea;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Sidebar;
use Gcego\Layouts\Components\Spacer;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Components\Sticky;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Diagnostics\LayoutInspector;
use Gcego\Layouts\Registry\LayoutKind;
use Gcego\Layouts\Rendering\LayoutRenderer;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Rendering\Renderers\RendererRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/layouts; also the "gcego/layouts" GCEGO package.
 *
 * Registers the "layout" registry kind (in register(), so discovery sees
 * it), one runtime renderer for every layout component, views,
 * translations, the anonymous <x-gcego-layouts::layout> component and the
 * styling slots of each component. Nothing is queried, rendered or loaded
 * from the registry while booting.
 */
final class LayoutsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-layouts';

    /**
     * Every component with its own styling slots.
     *
     * @var list<class-string<LayoutComponent>>
     */
    public const array Components = [
        Layout::class, Container::class, Grid::class, Row::class, Stack::class, Section::class, Card::class, Actions::class, Footer::class,
        Split::class, Sidebar::class, Main::class, Aside::class, Tabs::class, Tab::class, Accordion::class, AccordionItem::class,
        Collapsible::class, Divider::class, Spacer::class, ScrollArea::class, Sticky::class, EmptyState::class, Loading::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-layouts.php', 'gcego-layouts');

        $this->app->singleton(LayoutsConfig::class, static function (Application $app): LayoutsConfig {
            $config = $app->make(Repository::class)->get('gcego-layouts', []);

            return LayoutsConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(LayoutManager::class);
        $this->app->singleton(LayoutInspector::class);

        // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
        $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new LayoutKind));
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        Blade::anonymousComponentPath($root.'/resources/views/components', self::Views);
        $this->publishes([$root.'/config/gcego-layouts.php' => $this->app->configPath('gcego-layouts.php')], 'gcego-layouts-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-layouts-views');

        $this->app->make(LayoutsConfig::class);
        $this->app->make(RendererRegistry::class)->register(LayoutComponent::class, LayoutRenderer::class);

        $styling = $this->app->make(ChromaManager::class);

        foreach (self::Components as $component) {
            $styling->defineSlots($component, array_map(
                static fn (string $slot, string $description): SlotDefinition => new SlotDefinition($slot, description: $description),
                array_keys($component::styleSlots()),
                $component::styleSlots(),
            ), inherit: false);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/layouts',
            requires: ['gcego/core', 'gcego/registry', 'gcego/rendering', 'gcego/chroma', 'gcego/security', 'gcego/tables'],
            optional: ['gcego/widgets', 'gcego/forms', 'gcego/infolists', 'gcego/actions'],
            capabilities: ['layouts', 'layouts.responsive', 'layouts.tabs', 'layouts.disclosure', 'layouts.registry'],
            description: 'Declarative layouts: containers, responsive grids, rows, stacks, sections, cards, splits, sidebars, tabs, accordions and utility regions.',
        );
    }
}
