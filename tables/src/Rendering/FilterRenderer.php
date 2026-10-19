<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Tables\Filters\TableFilter;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\TableManager;

/**
 * Renders a filter's control inside a table frame, bound to filters.<name>
 * of the TableHost (the bound value is untrusted and normalized on the server).
 */
final class FilterRenderer extends BladeRenderer
{
    private string $view = 'gcego-tables::filters.select';

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = app(TableRenderContext::class)->current();

        if ($frame === null || ! $component instanceof TableFilter) {
            return RenderResult::empty();
        }

        $this->view = $component->view();

        return parent::render($component, $context);
    }

    protected function view(): string
    {
        return $this->view;
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(TableRenderContext::class)->current();
        $filter = $component instanceof TableFilter ? $component : null;
        $name = $filter?->filterName() ?? '';

        return [
            'frame' => $frame,
            'filter' => $filter,
            'name' => $name,
            'id' => $frame?->id('filter-'.$name) ?? 'filter-'.$name,
            'state' => $frame?->state->filters[$name] ?? null,
            'binding' => 'filters.'.$name,
            'model' => $frame === null ? null : app(TableManager::class)->model($frame->table),
            'presets' => TablePresets::class,
        ];
    }
}
