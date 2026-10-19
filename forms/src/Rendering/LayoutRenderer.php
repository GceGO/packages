<?php

declare(strict_types=1);

namespace Gcego\Forms\Rendering;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Layout\LayoutComponent;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Presets\FormPresets;
use Gcego\Forms\Reactive\ConditionEvaluator;
use Gcego\Forms\Schema\FieldEntry;
use Gcego\Runtime\Blade\BladeRenderer;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Illuminate\Contracts\View\Factory;

/**
 * Renders Section, Grid, Tabs, Tab, Wizard and Step. A layout whose
 * VisibleWhen fails renders nothing (its fields are not validated or saved either).
 */
final class LayoutRenderer extends BladeRenderer
{
    private string $view = 'gcego-forms::layouts.section';

    public function __construct(Factory $views, ComponentRenderer $renderer, StylingResolver $styling, private readonly FormRenderContext $frames)
    {
        parent::__construct($views, $renderer, $styling);
    }

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $frame = $this->frames->current();

        if (! $component instanceof LayoutComponent || $frame === null) {
            return RenderResult::empty();
        }

        $scope = $this->scope($frame, $context->path());
        $item = $scope === null ? null : (new ConditionEvaluator)->itemPathFor($scope->repeater ?? '', $frame->keys);

        foreach ($component->configurations()->ofClass(VisibleWhen::class) as $condition) {
            if (! $condition->condition->passes($frame->state, $item, $scope->siblings ?? [])) {
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
        $frame = $this->frames->current();

        return [
            'heading' => $component instanceof LayoutComponent && $component->heading() !== null ? (string) __($component->heading()) : null,
            'description' => is_string($description = $component->get(LayoutComponent::Description)) ? (string) __($description) : null,
            'icon' => $component instanceof Section ? $component->icon() : null,
            'grid' => $component instanceof Grid ? implode(' ', FormPresets::Grid[$component->columns()] ?? FormPresets::Grid[2]) : '',
            'labels' => array_map(static fn (ComponentInterface $child): string => $child instanceof LayoutComponent ? (string) __($child->heading() ?? '') : '', $component->children()->all()),
            'step' => $frame->step ?? 0,
            'live' => $frame?->isLive() ?? false,
            'presets' => FormPresets::class,
        ];
    }

    /**
     * A field inside this layout, when the layout sits in a repeater (it gives the item scope).
     */
    private function scope(FormRenderFrame $frame, string $path): ?FieldEntry
    {
        foreach ($frame->schema->entries as $entry) {
            if ($entry->repeater !== null && str_starts_with($entry->componentPath, $path.'.')) {
                return $entry;
            }
        }

        return null;
    }
}
