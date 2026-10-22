<?php

declare(strict_types=1);

namespace Gcego\Widgets;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Tailwind;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Widgets\Charts\ChartAdapters;
use Gcego\Widgets\Charts\ChartDataNormalizer;
use Gcego\Widgets\Charts\ChartJsAdapter;
use Gcego\Widgets\Data\WidgetDataResolver;
use Gcego\Widgets\Diagnostics\WidgetInspector;
use Gcego\Widgets\Livewire\WidgetHost;
use Gcego\Widgets\Presets\WidgetPresets;
use Gcego\Widgets\Registry\WidgetKind;
use Gcego\Widgets\Rendering\WidgetRenderContext;
use Gcego\Widgets\Rendering\WidgetRenderer;
use Gcego\Widgets\Statistics\Stats;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/widgets; also the "gcego/widgets" GCEGO package.
 *
 * Registers the "widget" registry kind (in register(), so discovery sees
 * it), the chart adapters, the runtime renderer, the Livewire host, views,
 * translations, the anonymous <x-gcego-widgets::widget> component and the
 * styling slots. Boot never queries or loads widget definitions.
 */
final class WidgetsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-widgets';

    public const string LivewireAlias = 'gcego-widget';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-widgets.php', 'gcego-widgets');

        $this->app->singleton(WidgetsConfig::class, static function (Application $app): WidgetsConfig {
            $config = $app->make(Repository::class)->get('gcego-widgets', []);

            return WidgetsConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(WidgetManager::class);
        $this->app->singleton(ChartAdapters::class, static function (Application $app): ChartAdapters {
            $adapters = new ChartAdapters;
            $adapters->register(new ChartJsAdapter($app->make(WidgetsConfig::class)));

            return $adapters;
        });
        $this->app->singleton(ChartDataNormalizer::class);
        $this->app->scoped(WidgetDataResolver::class);
        $this->app->scoped(WidgetRenderContext::class);
        $this->app->scoped(WidgetInspector::class);

        if (class_exists(DefinitionKinds::class)) {
            // After resolution, not now: Laravel's package discovery may register this provider before gcego/registry binds DefinitionKinds.
            $this->callAfterResolving(DefinitionKinds::class, static fn (DefinitionKinds $kinds) => $kinds->register(new WidgetKind));
        }
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Views);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Views);
        Blade::anonymousComponentPath($root.'/resources/views/components', self::Views);
        $this->publishes([$root.'/config/gcego-widgets.php' => $this->app->configPath('gcego-widgets.php')], 'gcego-widgets-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-widgets-views');

        $this->app->make(WidgetsConfig::class);

        Livewire::component(self::LivewireAlias, WidgetHost::class);

        $this->app->make(RendererRegistry::class)->register(Widget::class, WidgetRenderer::class);

        $styling = $this->app->make(ChromaManager::class);
        $styling->defineSlots(Widget::class, [
            new SlotDefinition(Tailwind::Wrapper, classes: WidgetPresets::Card),
            new SlotDefinition(Tailwind::Header, classes: WidgetPresets::Header),
            new SlotDefinition(Tailwind::Title, classes: WidgetPresets::Title),
            new SlotDefinition(Tailwind::Description, classes: WidgetPresets::Description),
            new SlotDefinition(Tailwind::Body, classes: WidgetPresets::Body),
            new SlotDefinition(Tailwind::Footer, classes: WidgetPresets::Footer),
            new SlotDefinition(Widget::ValueSlot, classes: WidgetPresets::Value),
            new SlotDefinition(Widget::TrendSlot, classes: WidgetPresets::Trend),
            new SlotDefinition(Widget::ChartSlot, classes: WidgetPresets::ChartArea),
            new SlotDefinition(Widget::LegendSlot, classes: WidgetPresets::Legend),
            new SlotDefinition(Widget::EmptySlot, classes: WidgetPresets::EmptyState),
            new SlotDefinition(Widget::LoadingSlot, classes: WidgetPresets::Loading),
        ]);
        $styling->defineSlots(Stats::class, [
            new SlotDefinition(Tailwind::Wrapper),
            new SlotDefinition(Tailwind::Title, classes: WidgetPresets::SectionTitle),
        ]);
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/widgets',
            requires: ['gcego/core', 'gcego/registry', 'gcego/rendering', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/tables', 'gcego/infolists'],
            capabilities: ['widgets', 'widgets.charts', 'widgets.livewire', 'widgets.aggregation', 'widgets.cache'],
            description: 'Dashboard widgets: KPIs with trends, charts, progress, goals, activity, record summaries and table widgets.',
        );
    }
}
