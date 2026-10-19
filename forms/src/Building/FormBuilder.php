<?php

declare(strict_types=1);

namespace Gcego\Forms\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Forms\Configuration\Validate;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Layout\Section;
use Gcego\Forms\Layout\Step;
use Gcego\Forms\Layout\Tab;
use Gcego\Forms\Layout\Tabs;
use Gcego\Forms\Layout\Wizard;

/**
 * The fluent form of a Form. Besides its own settings, it makes layouts, so
 * a form callback reads top to bottom:
 *
 *     fn (FormBuilder $form) => $form->grid(3, itemsStart: true)->schema([
 *         $form->section('Personal details')->columns(2)->schema([...]),
 *     ])
 *
 * grid(), section(), tabs(), tab(), wizard() and step() return new layout
 * builders; they do not change the form.
 */
class FormBuilder extends ComponentBuilder
{
    public function grid(int $columns = 2, bool $itemsStart = false): LayoutBuilder
    {
        return Grid::define($columns)->itemsStart($itemsStart);
    }

    public function section(?string $heading = null): LayoutBuilder
    {
        return Section::define($heading);
    }

    public function tabs(): LayoutBuilder
    {
        return Tabs::define();
    }

    public function tab(string $heading): LayoutBuilder
    {
        return Tab::define($heading);
    }

    public function wizard(): LayoutBuilder
    {
        return Wizard::define();
    }

    public function step(string $heading): LayoutBuilder
    {
        return Step::define($heading);
    }

    /**
     * Cross-field validation after the field rules pass: fn (Validator $validator, FormState $state, ?Model $record): void.
     */
    public function validateUsing(Closure $after): static
    {
        return $this->configure(new Validate($after));
    }
}
