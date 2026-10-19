<?php

declare(strict_types=1);

namespace Gcego\Actions\Rendering;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Livewire\Livewire;

/**
 * gcego/runtime renderer for Action and ActionGroup parts: mounts the
 * Livewire ActionHost for those actions of the registered blueprint being
 * rendered. The record comes from the render metadata ("record"), set by
 * the trusted page or table that renders a row.
 *
 * Blueprints rendered without a registry identity cannot execute actions
 * (only registered definitions are trusted), so nothing is rendered for them.
 */
final class ActionRenderer implements RendererInterface
{
    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if ($context->identity === null) {
            return RenderResult::empty();
        }

        $names = match (true) {
            $component instanceof ActionGroup => array_map(static fn (Action $action): string => $action->actionName(), $component->actions()),
            $component instanceof Action => [$component->actionName()],
            default => [],
        };
        $record = $context->metadata['record'] ?? null;

        $html = Livewire::mount('gcego-actions', [
            'definition' => $context->identity,
            'actions' => $names,
            'record' => is_int($record) || is_string($record) ? $record : null,
        ], 'gcego-actions-'.md5($context->identity.'|'.$context->path().'|'.(is_scalar($record) ? (string) $record : '')));

        return RenderResult::trustedHtml((string) $html);
    }
}
