<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Entries\RepeatableEntry;
use Gcego\Infolists\Presets\InfolistPresets;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;

/**
 * Renders one entry inside an infolist frame, as a <div> holding <dt>/<dd>.
 *
 * In order: Hidden, entry access (unreadable: nothing, and the value is
 * never read), VisibleWhen conditions, value and formatting,
 * HiddenWhenEmpty. Masked entries show a mask without reading the value.
 */
final class EntryRenderer extends BladeRenderer
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = app(InfolistRenderContext::class)->current();

        if ($frame === null || ! $component instanceof Entry || $component->isHidden()) {
            return RenderResult::empty();
        }

        $level = $frame->access->level($frame->path($component->entryName()));

        if ($level === FieldAccess::Hidden) {
            return RenderResult::empty();
        }

        foreach ($component->conditions() as $condition) {
            if (! $condition->passes($frame->record, $frame->readable(...))) {
                return RenderResult::empty();
            }
        }

        return parent::render($component, $context);
    }

    protected function view(): string
    {
        return 'gcego-infolists::entries.entry';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(InfolistRenderContext::class)->current();
        $entry = $component instanceof Entry ? $component : null;

        if ($frame === null || $entry === null) {
            return ['skip' => true];
        }

        $path = $frame->path($entry->entryName());
        $masked = $frame->access->level($path) === FieldAccess::Redacted;
        $display = $masked ? ['text' => null] : $entry->display($entry->value($frame->record), $frame->record, $frame->config);

        if (! $masked && $entry->hidesWhenEmpty() && $entry->isEmpty($display)) {
            return ['skip' => true];
        }

        $items = [];

        if (! $masked && $entry instanceof RepeatableEntry) {
            $children = $this->renderer->children($context);

            foreach ($entry->items($entry->value($frame->record), $frame->config) as $item) {
                $items[] = [
                    'key' => $item['key'],
                    'groups' => app(InfolistRenderContext::class)->run($frame->withItem($path, $item['item'], $item['key']), static fn (): array => Groups::from($children)),
                ];
            }
        }

        return [
            'skip' => false,
            'entry' => $entry,
            'frame' => $frame,
            'path' => $path,
            'id' => $frame->id('entry-'.$path),
            'masked' => $masked,
            'cell' => $display,
            'items' => $items,
            'placeholder' => $entry->placeholder($frame->config),
            'columns' => $frame->scope === null ? $frame->config->defaultColumns : 1,
            'presets' => InfolistPresets::class,
            'tablePresets' => TablePresets::class,
            'icons' => app(Icons::class),
        ];
    }
}
