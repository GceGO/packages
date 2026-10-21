<?php

declare(strict_types=1);

namespace Gcego\Forms\Building;

use Closure;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Component;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Forms\Configuration\Condition;
use Gcego\Forms\Configuration\VisibleWhen;
use Gcego\Forms\Layout\Grid;
use Gcego\Forms\Presets\FormPresets;

/**
 * The fluent form of a form layout (Grid, Section, Tabs, Tab, Wizard, Step):
 *
 *     Section::define('Contact')->icon('heroicon:envelope')->columns(2)->schema([...])
 *     Grid::define(3)->itemsStart()->schema([
 *         Grid::define(1)->columnSpan(2)->schema([...]),
 *         Grid::define(1)->sticky(top: 6)->schema([...]),
 *     ])
 */
class LayoutBuilder extends ComponentBuilder
{
    private ?int $columns = null;

    /**
     * A grid's column count; on any other layout, lay its children out in a grid of that many columns.
     */
    public function columns(int $columns): static
    {
        if (is_a($this->component, Grid::class, true)) {
            return $this->set(Grid::Columns, $columns);
        }

        if ($columns < 1 || $columns > 4) {
            throw InvalidBuilderException::invalidValue($this->component, 'columns', 'a layout has between 1 and 4 columns.');
        }

        $copy = clone $this;
        $copy->columns = $columns;

        return $copy;
    }

    /**
     * Span columns of the parent grid.
     */
    public function columnSpan(int $span): static
    {
        return $this->tailwind('columnSpan', FormPresets::Span[$span] ?? null, 'a layout spans between 1 and 4 columns.');
    }

    /**
     * Stay in view while the page scrolls (on large screens), the given spacing from the top.
     */
    public function sticky(int $top = 6): static
    {
        return $this->tailwind('sticky', isset(FormPresets::Sticky[$top]) ? implode(' ', FormPresets::Sticky[$top]) : null, 'top must be one of '.implode(', ', array_keys(FormPresets::Sticky)).'.');
    }

    /**
     * Align a grid's children to the top instead of stretching them to the row's height.
     */
    public function itemsStart(bool $itemsStart = true): static
    {
        return $itemsStart ? $this->tailwind('itemsStart', implode(' ', FormPresets::ItemsStart), '') : $this;
    }

    /**
     * Show the layout (and validate and save its fields) only while another field matches.
     *
     * @param  scalar|\BackedEnum|list<scalar|\BackedEnum>|null  $value
     */
    public function visibleWhen(string|Condition $field, mixed $value = true): static
    {
        return $this->configure(new VisibleWhen($field, $value));
    }

    /**
     * Show the layout (and validate and save its fields) only while the closure returns true.
     */
    public function visible(Closure $condition): static
    {
        return $this->configure(new VisibleWhen(Condition::computed($condition)));
    }

    public function hidden(Closure $condition): static
    {
        return $this->configure(new VisibleWhen(Condition::computed($condition, negate: true)));
    }

    public function build(): Component
    {
        if ($this->columns === null) {
            return parent::build();
        }

        $copy = clone $this;
        $copy->columns = null;

        return $copy->withChildItems([Grid::define($this->columns)->schema($this->childItems())])->build();
    }

    private function tailwind(string $method, ?string $classes, string $reason): static
    {
        if ($classes === null) {
            throw InvalidBuilderException::invalidValue($this->component, $method, $reason);
        }

        return $classes === '' ? $this : $this->configure(new Tailwind(...explode(' ', $classes)));
    }
}
