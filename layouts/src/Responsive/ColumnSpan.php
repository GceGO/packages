<?php

declare(strict_types=1);

namespace Gcego\Layouts\Responsive;

use Gcego\Components\Contracts\PlacementConfiguration;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Presets\LayoutPresets;

/**
 * How many grid columns a child spans: 1 to 6, "full" (the whole row) or
 * "auto". Put it on any child of a Grid (every component accepts it):
 *
 *     new Stat('Revenue', …, new ColumnSpan(2))
 *     new Chart(…, new ColumnSpan(['default' => 'full', 'xl' => 2]))
 *
 * At each breakpoint the span is limited to the grid's columns there, so a
 * span never creates extra columns or horizontal overflow: on a one-column
 * phone layout every child is full width.
 */
final readonly class ColumnSpan implements PlacementConfiguration
{
    public ResponsiveValue $span;

    /**
     * @param  int|string|array<string, int|string>  $span
     */
    public function __construct(int|string|array $span)
    {
        $this->span = ResponsiveValue::of($span, static fn (mixed $value): bool => $value === 'full' || $value === 'auto' || (is_int($value) && $value >= 1 && $value <= Columns::Maximum), 'ColumnSpan', '1 to 6, "full" or "auto"');
    }

    /**
     * The largest numeric span requested at any breakpoint (0 for full or auto only).
     */
    public function largest(): int
    {
        $spans = array_filter($this->span->values, is_int(...));

        return $spans === [] ? 0 : max($spans);
    }

    /**
     * Classes for this span in a grid with these columns.
     */
    public function classes(Columns $columns): string
    {
        return self::spanClasses($this->span, $columns);
    }

    /**
     * @return int|string|array<string, int|string>
     */
    public function normalize(): int|string|array
    {
        return $this->span->normalize();
    }

    /**
     * Span classes, limited to the columns at each breakpoint.
     */
    public static function spanClasses(ResponsiveValue $span, Columns $columns): string
    {
        $classes = [];
        $previous = 1;

        foreach (Breakpoint::cases() as $breakpoint) {
            $value = $span->at($breakpoint) ?? 1;
            $effective = is_int($value) ? min($value, $columns->at($breakpoint)) : $value;

            if ($effective !== $previous) {
                $classes[] = LayoutPresets::ColumnSpans[$breakpoint->value][$effective];
            }

            $previous = $effective;
        }

        return implode(' ', $classes);
    }
}
