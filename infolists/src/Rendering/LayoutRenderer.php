<?php

declare(strict_types=1);

namespace Gcego\Infolists\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Infolists\Layout\LayoutComponent;
use Gcego\Infolists\Layout\Split;
use Gcego\Infolists\Layout\Tab;
use Gcego\Infolists\Layout\Tabs;
use Gcego\Infolists\Presets\InfolistPresets;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Support\Icons;

/**
 * Renders Section, Grid, Tabs, Tab and Split inside an infolist frame.
 * Requirements and VisibleWhen conditions are checked first (a hidden
 * layout's entries are never resolved); a layout whose children all render
 * nothing renders nothing.
 */
final class LayoutRenderer extends BladeRenderer
{
    private string $view = 'gcego-infolists::layouts.section';

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = app(InfolistRenderContext::class)->current();

        if ($frame === null || ! $component instanceof LayoutComponent) {
            return RenderResult::empty();
        }

        if ($component->requirements() !== [] && app(SecurityManager::class)->checkFor($component->requirements(), $frame->root ?? $frame->record)->denied()) {
            return RenderResult::empty();
        }

        foreach ($component->conditions() as $condition) {
            if (! $condition->passes($frame->record, $frame->readable(...))) {
                return RenderResult::empty();
            }
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
        $frame = app(InfolistRenderContext::class)->current();
        $children = $this->renderer->children($context);
        $layout = $component instanceof LayoutComponent ? $component : null;
        $columns = $layout?->columns($frame !== null ? $frame->config->defaultColumns : 2) ?? 2;
        $panels = [];
        $parts = [];

        if ($component instanceof Tabs) {
            foreach ($children->nodes() as $index => $node) {
                $html = $children->render($index)->toHtml();

                if (trim($html) !== '' && $node->component instanceof Tab) {
                    $panels[] = ['label' => (string) ($node->component->heading() ?? ''), 'icon' => $node->component->get(Tab::Icon), 'html' => $html];
                }
            }
        } elseif ($component instanceof Split) {
            foreach (array_keys($children->nodes()) as $index) {
                $parts[] = Groups::from($children, [$index]);
            }
        }

        $groups = $panels === [] && $parts === [] ? Groups::from($children) : [];

        return [
            'skip' => $groups === [] && $panels === [] && array_merge(...($parts ?: [[]])) === [],
            'layout' => $layout,
            'frame' => $frame,
            'groups' => $groups,
            'panels' => $panels,
            'parts' => $parts,
            'grid' => InfolistPresets::Grids[$columns] ?? InfolistPresets::Grids[2],
            'heading' => $layout?->heading(),
            'description' => $layout?->description(),
            'presets' => InfolistPresets::class,
            'icons' => app(Icons::class),
        ];
    }
}
