<?php

declare(strict_types=1);

namespace Gcego\Widgets\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Gcego\Widgets\Presets\WidgetPresets;
use Gcego\Widgets\Statistics\Stats;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetManager;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Throwable;

/**
 * Renders every widget type.
 *
 * - Inside a frame (WidgetManager / WidgetHost): checks the requirements down
 *   to this widget (an unauthorized child renders nothing), resolves its data
 *   and renders its view. Failing providers render the error state (the
 *   exception is reported, never shown); authorization failures of the
 *   underlying data hide the widget.
 * - Rendered by identity through gcego/runtime ("widget:…"): the Livewire host
 *   for widgets that need it (date range, refresh, feed pages) in Livewire
 *   mode, otherwise a static render.
 * - Embedded in a registered layout or blueprint (a child occurrence of
 *   "layout:…" or "blueprint:…"): authorized through the owner, inheriting
 *   its requirements down to the widget, then rendered the same way (the
 *   host is mounted with the owner identity and the widget's path).
 * - A widget without a registered identity or owner is refused.
 */
final class WidgetRenderer extends BladeRenderer
{
    private string $view = 'gcego-widgets::statistics.stat';

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof Widget) {
            return RenderResult::empty();
        }

        $frame = app(WidgetRenderContext::class)->current();

        if ($frame !== null) {
            if (! $context->node->isRoot() && ! app(WidgetManager::class)->allows($frame->root, $frame->path($context->path()))) {
                return RenderResult::empty();
            }

            $this->view = $component->view();

            return parent::render($component, $context);
        }

        if ($context->identity !== null && str_starts_with($context->identity, 'widget:')) {
            if ($context->isLivewire() && $component->needsLivewire()) {
                return RenderResult::trustedHtml((string) Livewire::mount('gcego-widget', ['widget' => $context->identity], 'gcego-widget-'.md5($context->identity)));
            }

            return RenderResult::trustedHtml(app(WidgetManager::class)->render($context->identity)->toHtml());
        }

        if ($context->identity !== null && ! $context->node->isRoot()) {
            return $this->embedded($component, $context, $context->identity);
        }

        throw InvalidWidgetException::invalid('Widget "'.$component->widgetName().'"', 'widgets render by their registry identity (WidgetManager::render("widget:…") or <livewire:gcego-widget>).');
    }

    /**
     * A widget inside a registered layout or blueprint, authorized through its owner.
     */
    private function embedded(Widget $component, RenderContext $context, string $owner): RenderResult
    {
        $manager = app(WidgetManager::class);
        $record = $context->metadata['record'] ?? null;
        $record = is_int($record) || is_string($record) ? $record : null;
        [$root, $definition] = $manager->locate($owner, $context->path());

        if ($definition::class !== $component::class) {
            throw InvalidWidgetException::invalid('Widget "'.$component->widgetName().'"', 'it is not the widget registered at this path of "'.$owner.'".');
        }

        if ($context->isLivewire() && $component->needsLivewire()) {
            if (! $manager->allows($root, $context->path())) {
                return RenderResult::empty();
            }

            return RenderResult::trustedHtml((string) Livewire::mount('gcego-widget', ['widget' => $owner, 'path' => $context->path(), 'record' => $record], 'gcego-widget-'.md5($owner.'|'.$context->path().'|'.(string) $record)));
        }

        $frame = new WidgetRenderFrame($owner, $root, $manager->state($component), $record, 'gcego-widget-'.substr(hash('sha256', $owner.'|'.$context->path()), 0, 8));

        return RenderResult::trustedHtml(app(WidgetRenderContext::class)->run($frame, fn (): string => $this->render($component, $context)->toHtml()));
    }

    protected function view(): string
    {
        return $this->view;
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(WidgetRenderContext::class)->current();
        $widget = $component instanceof Widget ? $component : null;

        if ($frame === null || $widget === null) {
            return ['skip' => true];
        }

        $manager = app(WidgetManager::class);
        $error = false;
        $view = [];
        $children = [];

        try {
            $result = $manager->data($widget, $manager->context($frame->identity, $widget, $frame->state, $frame->record, $frame->path($context->path())), $frame->shared);
            $view = $widget->viewData($result, $manager->config(), $frame);

            if ($widget instanceof Stats) {
                $renderer = $this->renderer->children($context);
                $children = app(WidgetRenderContext::class)->run($frame->withShared($result), static function () use ($renderer): array {
                    $html = [];

                    foreach (array_keys($renderer->nodes()) as $index) {
                        $rendered = $renderer->render($index)->toHtml();

                        if (trim($rendered) !== '') {
                            $html[] = $rendered;
                        }
                    }

                    return $html;
                });
            }
        } catch (AuthorizationException) {
            return ['skip' => true];
        } catch (Throwable $exception) {
            report($exception);
            $error = true;
        }

        if ($widget instanceof Stats && ! $error && $children === []) {
            return ['skip' => true];
        }

        return [
            ...$view,
            'skip' => false,
            'widget' => $widget,
            'frame' => $frame,
            'error' => $error,
            'empty' => $view['empty'] ?? false,
            'items' => $view['items'] ?? [],
            'stats' => $children,
            'title' => $widget->title(),
            'description' => $widget->description(),
            'icon' => is_string($icon = $widget->get(Widget::Icon)) ? $icon : null,
            'headingId' => $frame->id($context->path().'-title'),
            'root' => $context->node->isRoot(),
            'presets' => WidgetPresets::class,
            'tablePresets' => TablePresets::class,
            'icons' => app(Icons::class),
        ];
    }
}
