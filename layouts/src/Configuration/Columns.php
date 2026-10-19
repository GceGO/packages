<?php

declare(strict_types=1);

namespace Gcego\Layouts\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Responsive\Breakpoint;
use Gcego\Layouts\Responsive\ResponsiveValue;

/**
 * Grid columns (1 to 6).
 *
 *     new Columns(3)                                     // responsive: 1, 2 from sm, 3 from lg
 *     new Columns(['default' => 1, 'md' => 2, 'xl' => 4]) // exactly this
 *
 * A single count is the widest layout; narrower screens get fewer columns
 * (1; 2 from sm; the count from lg, or 3 from md and the count from xl for
 * five or six columns). A breakpoint map is used as given, with 1 column
 * below its first breakpoint.
 */
final readonly class Columns implements ConfigurationNode
{
    public const int Maximum = 6;

    public ResponsiveValue $columns;

    /**
     * @param  int|array<string, int>  $columns
     */
    public function __construct(int|array $columns)
    {
        $value = ResponsiveValue::of($columns, static fn (mixed $count): bool => is_int($count) && $count >= 1 && $count <= self::Maximum, 'Columns', 'a whole number from 1 to 6');

        $this->columns = is_int($columns) ? self::expand($columns) : ResponsiveValue::of([Breakpoint::Default->value => 1, ...$value->values], static fn (): bool => true, 'Columns', '');
    }

    /**
     * Professional defaults for a grid of $items children without Columns: at most four columns.
     */
    public static function auto(int $items): self
    {
        return new self(max(1, min(4, $items)));
    }

    /**
     * The columns in effect at a breakpoint.
     */
    public function at(Breakpoint $breakpoint): int
    {
        $count = $this->columns->at($breakpoint);

        return is_int($count) ? $count : 1;
    }

    /**
     * The most columns at any width.
     */
    public function maximum(): int
    {
        return max(array_map(fn (Breakpoint $breakpoint): int => $this->at($breakpoint), Breakpoint::cases()));
    }

    public function classes(): string
    {
        return $this->columns->classes(LayoutPresets::GridColumns);
    }

    /**
     * @return int|string|array<string, int|string>
     */
    public function normalize(): int|string|array
    {
        return $this->columns->normalize();
    }

    private static function expand(int $count): ResponsiveValue
    {
        $values = match (true) {
            $count === 1 => ['default' => 1],
            $count === 2 => ['default' => 1, 'sm' => 2],
            $count <= 4 => ['default' => 1, 'sm' => 2, 'lg' => $count],
            default => ['default' => 1, 'sm' => 2, 'md' => 3, 'xl' => $count],
        };

        return ResponsiveValue::of($values, static fn (): bool => true, 'Columns', '');
    }
}
