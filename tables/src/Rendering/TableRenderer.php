<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Filters\TableFilter;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\Table;
use Livewire\Livewire;

/**
 * Renders a Table.
 *
 * - Inside a TableHost (a render frame): the table markup for the frame.
 * - Inside a registered blueprint (the runtime's Livewire host or a static
 *   page render): mounts <livewire:gcego-table> for that blueprint, which
 *   resolves, authorizes and queries on the server.
 * - A bare Table has no trusted data source and is refused.
 */
final class TableRenderer extends BladeRenderer
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof Table) {
            return RenderResult::empty();
        }

        if (app(TableRenderContext::class)->current() !== null) {
            return parent::render($component, $context);
        }

        if ($context->identity !== null && ! $context->node->isRoot()) {
            return RenderResult::trustedHtml((string) Livewire::mount('gcego-table', ['definition' => $context->identity], 'gcego-table-'.md5($context->identity.'|'.$context->path())));
        }

        throw InvalidTableException::invalid('Table "'.$component->tableName().'"', 'a table renders inside a registered blueprint with a Source (<livewire:gcego-table definition="…" />).');
    }

    protected function view(): string
    {
        return 'gcego-tables::tables.table';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(TableRenderContext::class)->current();
        $children = $this->renderer->children($context);
        $headers = [];
        $filters = [];

        foreach ($children->nodes() as $index => $node) {
            if ($node->component instanceof Column) {
                $headers[$node->component->columnName()] = $index;
            }

            if ($node->component instanceof TableFilter && $frame?->table->schema->filter($node->component->filterName()) !== null) {
                $filters[] = $index;
            }
        }

        return [
            'frame' => $frame,
            'table' => $component,
            'schema' => $frame?->table->schema,
            'headerIndexes' => $headers,
            'filterIndexes' => $filters,
            'presets' => TablePresets::class,
            'icons' => app(Icons::class),
        ];
    }
}
