<?php

declare(strict_types=1);

namespace Gcego\Widgets\Diagnostics;

use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Widgets\Activity\ActivitySource;
use Gcego\Widgets\Charts\ChartAdapters;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DataProvider;
use Gcego\Widgets\Data\Value;
use Gcego\Widgets\Records\RecordSummary;
use Gcego\Widgets\Records\TableWidget;
use Gcego\Widgets\Statistics\Stats;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetManager;
use Gcego\Widgets\WidgetsConfig;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Describes a widget without resolving any data: type, data source,
 * refresh and cache policy, layout, and findings:
 *
 * schema, registry (not registered), authorization (no requirement: denied),
 * source (no data source, missing blueprint, model without a policy),
 * refresh (polling below the minimum, clamped), cache (tenant-shared caches,
 * caching disabled globally), chart (unknown adapter), renderer, styling,
 * performance (closure values that may bypass gcego/data, large groups,
 * polling). Never runs providers, closures or aggregates.
 */
final readonly class WidgetInspector
{
    public function __construct(private Container $container, private WidgetsConfig $config) {}

    public function inspect(string|Widget $definition): WidgetReport
    {
        $identity = null;

        try {
            if (is_string($definition)) {
                $manager = $this->container->make(WidgetManager::class);
                $identity = $manager->identity($definition);
                $widget = $manager->resolve($identity);
            } else {
                $widget = $definition;
                $candidate = 'widget:'.$widget->widgetName();
                $identity = $this->container->make(RegistryInterface::class)->has($candidate) ? $candidate : null;
            }
        } catch (Throwable $exception) {
            return new WidgetReport(is_string($definition) ? $definition : $definition->widgetName(), null, [], [new WidgetDiagnostic('registry', WidgetDiagnostic::Error, 'The widget is not registered: '.$exception->getMessage())]);
        }

        $diagnostics = [];

        foreach ($widget->validate()->errors() as $error) {
            $diagnostics[] = new WidgetDiagnostic('schema', WidgetDiagnostic::Error, (string) $error);
        }

        if ($identity === null) {
            $diagnostics[] = new WidgetDiagnostic('registry', WidgetDiagnostic::Warning, sprintf('Widget "%s" is not registered; register it or place it in app/GCEGo/Widgets.', $widget->widgetName()));
        }

        if ($widget->requirements() === []) {
            $diagnostics[] = new WidgetDiagnostic('authorization', WidgetDiagnostic::Error, 'The widget declares no gcego/security requirement, so every user is denied; add a Permission or Ability node.');
        }

        $rows = [];
        $renderers = $this->container->bound(RendererRegistry::class) ? $this->container->make(RendererRegistry::class) : null;
        $slots = $this->container->bound(SlotRegistry::class) ? $this->container->make(SlotRegistry::class) : null;

        foreach ((new ComponentWalker)->preOrder($widget) as $node) {
            $child = $node->component;

            if (! $child instanceof Widget) {
                continue;
            }

            $source = $child->dataSource();
            $details = ['widget' => $node->path->toString()];
            $rows[] = [
                'path' => $node->path->toString(),
                'type' => $child::class,
                'source' => match (true) {
                    $source instanceof DataProvider => 'provider:'.$source->provider,
                    $source instanceof Aggregate => 'aggregate:'.$source->function->value.':'.$source->source,
                    $source instanceof ActivitySource => 'activity:'.$source->source,
                    $source instanceof Value => is_object($source->value) ? 'closure' : 'static',
                    default => null,
                },
                'refresh' => $child->refresh()?->every,
                'cache' => $child->cache()?->scope,
                'livewire' => $child->needsLivewire(),
                'span' => (string) $child->layout()['span'],
            ];

            if ($source === null && ! $child instanceof Stats && ! $child instanceof RecordSummary && ! $child instanceof TableWidget) {
                $diagnostics[] = new WidgetDiagnostic('source', WidgetDiagnostic::Warning, sprintf('Widget "%s" has no data source; it always shows its empty state.', $node->path->toString()), $details);
            }

            if ($source instanceof Value && is_object($source->value)) {
                $diagnostics[] = new WidgetDiagnostic('performance', WidgetDiagnostic::Ok, sprintf('Widget "%s" computes its value with a closure; use the context helpers (gcego/data) so tenant scopes and policies apply.', $node->path->toString()), $details);
            }

            foreach (array_filter([$source instanceof Aggregate ? $source->source : null, $source instanceof ActivitySource ? $source->source : null]) as $target) {
                if (str_starts_with($target, 'blueprint:')) {
                    if (! $this->container->make(RegistryInterface::class)->has($target)) {
                        $diagnostics[] = new WidgetDiagnostic('source', WidgetDiagnostic::Error, sprintf('Widget "%s" reads %s, which is not registered.', $node->path->toString(), $target), $details);
                    }
                } elseif ($this->container->make(Gate::class)->getPolicyFor($target) === null) {
                    $diagnostics[] = new WidgetDiagnostic('source', WidgetDiagnostic::Error, sprintf('Widget "%s" reads %s, which has no policy: gcego/data denies every user.', $node->path->toString(), class_basename($target)), $details);
                }
            }

            $refresh = $child->refresh();

            if ($refresh?->every !== null) {
                $diagnostics[] = new WidgetDiagnostic('refresh', $refresh->every < $this->config->minimumRefreshSeconds ? WidgetDiagnostic::Warning : WidgetDiagnostic::Ok, sprintf('Widget "%s" polls every %ds%s.', $node->path->toString(), max($refresh->every, $this->config->minimumRefreshSeconds), $refresh->every < $this->config->minimumRefreshSeconds ? ' (raised to the minimum)' : ''), $details);
            }

            $cache = $child->cache();

            if ($cache !== null) {
                if (! $this->config->cacheEnabled) {
                    $diagnostics[] = new WidgetDiagnostic('cache', WidgetDiagnostic::Warning, sprintf('Widget "%s" has a Cache node but gcego-widgets.cache.enabled is false.', $node->path->toString()), $details);
                }

                if ($cache->scope === 'tenant') {
                    $diagnostics[] = new WidgetDiagnostic('cache', WidgetDiagnostic::Warning, sprintf('Widget "%s" shares cached data across the tenant\'s users; use it only for data that does not depend on the user.', $node->path->toString()), $details);
                }
            }

            if ($renderers !== null && ! $renderers->has($child)) {
                $diagnostics[] = new WidgetDiagnostic('renderer', WidgetDiagnostic::Error, sprintf('%s has no renderer.', $child::class), $details);
            }

            if ($slots !== null && $slots->for($child) === null) {
                $diagnostics[] = new WidgetDiagnostic('styling', WidgetDiagnostic::Warning, sprintf('%s has no styling slots.', $child::class), $details);
            }
        }

        if (! $this->container->make(ChartAdapters::class)->has($this->config->chartAdapter)) {
            $diagnostics[] = new WidgetDiagnostic('chart', WidgetDiagnostic::Error, sprintf('The chart adapter "%s" is not registered.', $this->config->chartAdapter));
        }

        if ($widget instanceof Stats && count($widget->stats()) > 8) {
            $diagnostics[] = new WidgetDiagnostic('performance', WidgetDiagnostic::Warning, 'More than 8 stats in one group: each resolves its own data on every render.');
        }

        if ($diagnostics === []) {
            $diagnostics[] = new WidgetDiagnostic('widget', WidgetDiagnostic::Ok, sprintf('Widget "%s" has no problems.', $widget->widgetName()));
        }

        return new WidgetReport($widget->widgetName(), $identity, $rows, $diagnostics);
    }
}
