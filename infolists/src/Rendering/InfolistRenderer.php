<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Gcego\Actions\ActionManager;
use Gcego\Actions\ActionPlacement;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\InfolistManager;
use Gcego\Infolists\Presets\InfolistPresets;
use Gcego\Rendering\Blade\BladeRenderer;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Tables\Support\Icons;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Throwable;

/**
 * Renders an Infolist.
 *
 * - Inside a frame (InfolistManager / InfolistHost): the record details.
 * - Inside a registered blueprint with a "record" key in the render
 *   metadata: the Livewire host when rendering in Livewire mode, otherwise
 *   a static server-side render (both resolve and authorize the record).
 *   Without a record key it renders nothing.
 * - A bare Infolist has no trusted source or record and is refused.
 */
final class InfolistRenderer extends BladeRenderer
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof Infolist) {
            return RenderResult::empty();
        }

        if (app(InfolistRenderContext::class)->current() !== null) {
            return parent::render($component, $context);
        }

        if ($context->identity !== null && ! $context->node->isRoot()) {
            $record = $context->metadata['record'] ?? null;

            if (! is_int($record) && ! is_string($record)) {
                return RenderResult::empty();
            }

            if ($context->isLivewire()) {
                return RenderResult::trustedHtml((string) Livewire::mount('gcego-infolist', ['definition' => $context->identity, 'record' => $record], 'gcego-infolist-'.md5($context->identity.'|'.$record)));
            }

            return RenderResult::trustedHtml(app(InfolistManager::class)->render($context->identity, $record)->toHtml());
        }

        throw InvalidEntryException::invalid('Infolist "'.$component->infolistName().'"', 'an infolist renders a record of a registered blueprint (InfolistManager::render() or <livewire:gcego-infolist>).');
    }

    protected function view(): string
    {
        return 'gcego-infolists::infolists.infolist';
    }

    protected function data(ComponentInterface $component, RenderContext $context): array
    {
        $frame = app(InfolistRenderContext::class)->current();
        $infolist = $component instanceof Infolist ? $component : null;
        $columns = $infolist?->columns($frame !== null ? $frame->config->defaultColumns : 2) ?? 2;
        $record = $frame?->record;
        $key = $record instanceof Model && (is_int($record->getKey()) || is_string($record->getKey())) ? $record->getKey() : null;

        return [
            'infolist' => $infolist,
            'frame' => $frame,
            'groups' => Groups::from($this->renderer->children($context)),
            'grid' => InfolistPresets::Grids[$columns] ?? InfolistPresets::Grids[2],
            'recordKey' => $key,
            'hasActions' => $frame !== null && $frame->live && $key !== null && $this->hasRecordActions($frame->definition),
            'presets' => InfolistPresets::class,
            'icons' => app(Icons::class),
        ];
    }

    private function hasRecordActions(string $definition): bool
    {
        try {
            return app(ActionManager::class)->actions($definition, ActionPlacement::Record) !== [];
        } catch (Throwable) {
            return false;
        }
    }
}
