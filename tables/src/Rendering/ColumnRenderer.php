<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;

/**
 * Renders a column's header cell (<th scope="col">) with its sort control,
 * inside a table frame. Cells are rendered by the table view with the
 * column's cellView(); columns render nothing outside a table.
 */
final class ColumnRenderer extends BladeRenderer
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = app(TableRenderContext::class)->current();

        if ($frame === null || ! $component instanceof Column || ! in_array($component->columnName(), $frame->state->visibleColumns(), true)) {
            return RenderResult::empty();
        }

        return parent::render($component, $context);
    }

    protected function view(): string
    {
        return 'gcego-tables::columns.header';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(TableRenderContext::class)->current();
        $column = $component instanceof Column ? $component : null;
        $name = $column?->columnName() ?? '';
        $sortable = $frame !== null && $column !== null && $column->isSortable() && $frame->access->canRead($name);
        $direction = $frame?->state->sortDirection($name);

        return [
            'frame' => $frame,
            'column' => $column,
            'name' => $name,
            'sortable' => $sortable,
            'direction' => $direction,
            'position' => $frame !== null && $frame->multiSort && count($frame->state->sorts) > 1 ? $frame->state->sortPosition($name) : null,
            'ariaSort' => match ($direction) {
                'asc' => 'ascending',
                'desc' => 'descending',
                default => $sortable ? 'none' : null,
            },
            'layout' => $column?->layoutClasses() ?? '',
            'presets' => TablePresets::class,
            'icons' => app(Icons::class),
        ];
    }
}
