<?php

declare(strict_types=1);

namespace Gcego\Layouts\Components;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Rendering\LayoutView;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Responsive\Visibility;
use Gcego\Widgets\Widget;

/**
 * A responsive grid, mobile first:
 *
 *     new Grid(new Stat(…), new Stat(…), new Stat(…))                // 1 column, 2 from sm, 3 from lg
 *     new Grid(new Columns(['default' => 1, 'md' => 2, 'xl' => 4]), …)
 *     new Grid(new Property(Grid::MinItemWidth, 'sm'), …)           // as many 16rem columns as fit
 *
 * Without Columns, the grid uses Columns::auto() for the children it
 * renders (at most four columns). Children span columns with ColumnSpan
 * (a widget's Span metadata is used when it has none); spans are limited to
 * the columns at each width, and a span larger than the most columns of an
 * explicit Columns fails validation. DOM order is the reading order: the
 * grid never reorders children.
 */
final class Grid extends LayoutComponent
{
    /** Auto-fit columns of at least xs (12rem), sm (16rem), md (20rem) or lg (24rem); spans other than "full" are ignored. */
    public const string MinItemWidth = 'min_item_width';

    /** Fill holes left by spanning children (changes visual order: use for independent cards only). */
    public const string Dense = 'dense';

    public const string Element = 'element';

    public function view(): string
    {
        return 'gcego-layouts::layouts.grid';
    }

    public function viewData(LayoutView $layout): array
    {
        $fit = $this->stringProperty(self::MinItemWidth);
        $columns = $fit === null ? $this->columns(count($layout->items))->classes() : LayoutPresets::AutoFit[$fit];

        return [
            'element' => $this->stringProperty(self::Element) ?? 'div',
            'grid' => implode(' ', [...LayoutPresets::GridBase, $columns, $this->gapClasses($layout->config->defaultGap), $this->alignClasses(), $this->boolProperty(self::Dense) ? implode(' ', LayoutPresets::Dense) : '']),
        ];
    }

    /**
     * The columns for $items rendered children.
     */
    public function columns(int $items): Columns
    {
        $columns = $this->configurations()->first(Columns::class);

        return $columns instanceof Columns ? $columns : Columns::auto($items);
    }

    public function placement(ComponentInterface $child, int $siblings, LayoutsConfig $config): string
    {
        $span = self::spanOf($child);
        $classes = '';

        if ($span !== null && ($this->stringProperty(self::MinItemWidth) === null || $span->span->values === ['default' => 'full'])) {
            $classes = $span->classes($this->columns($siblings));
        }

        $visibility = $child->configurations()->first(Visibility::class);
        $classes = trim($classes.' '.($visibility instanceof Visibility ? $visibility->classes() : ''));

        return $classes === '' ? '' : trim(implode(' ', LayoutPresets::Item).' '.$classes);
    }

    /**
     * A child's span: its ColumnSpan node, else a widget's Span metadata.
     */
    public static function spanOf(ComponentInterface $child): ?ColumnSpan
    {
        $span = $child->configurations()->first(ColumnSpan::class);

        if ($span instanceof ColumnSpan) {
            return $span;
        }

        if ($child instanceof Widget) {
            $metadata = $child->layout()['span'];

            return $metadata === 1 || $metadata === '1' ? null : new ColumnSpan(is_numeric($metadata) ? (int) $metadata : 'full');
        }

        return null;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                self::choice(self::MinItemWidth, array_keys(LayoutPresets::AutoFit)),
                new PropertyDefinition(self::Dense, Type::bool(), default: false),
                self::choice(self::Element, ['div', 'section'], 'div'),
            )
            ->withConstraint(static function (ComponentInterface $grid): array {
                $columns = $grid->configurations()->first(Columns::class);

                if (! $columns instanceof Columns) {
                    return [];
                }

                $errors = [];

                foreach ($grid->children() as $index => $child) {
                    $span = $child->configurations()->first(ColumnSpan::class);

                    if ($span instanceof ColumnSpan && $span->largest() > $columns->maximum()) {
                        $errors[] = sprintf('child %d spans %d columns, but the grid has at most %d.', $index, $span->largest(), $columns->maximum());
                    }
                }

                return $errors;
            });
    }

    protected static function layoutConfigurations(): array
    {
        return [...parent::layoutConfigurations(), Columns::class, Gap::class, Align::class];
    }
}
