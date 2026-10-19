<?php

declare(strict_types=1);

namespace Gcego\Layouts\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Responsive\ResponsiveValue;

/**
 * Space between the children of a grid, row, stack, layout or split, on the
 * spacing scale (0, 1, 2, 3, 4, 6, 8, 10, 12; 4 = 1rem):
 *
 *     new Gap(4)
 *     new Gap(['default' => 4, 'lg' => 8])
 *     new Gap(row: 8, column: 4)
 */
final readonly class Gap implements ConfigurationNode
{
    public ?ResponsiveValue $gap;

    /**
     * @param  int|array<string, int>|null  $gap
     */
    public function __construct(int|array|null $gap = null, public ?int $row = null, public ?int $column = null)
    {
        $step = static fn (mixed $value): bool => is_int($value) && in_array($value, LayoutPresets::GapSteps, true);
        $this->gap = $gap === null ? null : ResponsiveValue::of($gap, $step, 'Gap', 'a spacing step ('.implode(', ', LayoutPresets::GapSteps).')');

        foreach (['row' => $row, 'column' => $column] as $name => $value) {
            if ($value !== null && ! $step($value)) {
                throw InvalidLayoutException::invalid('Gap', sprintf('%s must be a spacing step (%s).', $name, implode(', ', LayoutPresets::GapSteps)));
            }
        }

        if ($gap === null && $row === null && $column === null) {
            throw InvalidLayoutException::invalid('Gap', 'set a gap, a row gap or a column gap.');
        }
    }

    public function classes(int $default): string
    {
        $classes = [$this->gap?->classes(LayoutPresets::Gaps) ?? ($this->row !== null && $this->column !== null ? '' : LayoutPresets::Gaps['default'][$default])];

        if ($this->row !== null) {
            $classes[] = LayoutPresets::RowGaps[$this->row];
        }

        if ($this->column !== null) {
            $classes[] = LayoutPresets::ColumnGaps[$this->column];
        }

        return trim(implode(' ', $classes));
    }

    /**
     * @return array{gap: int|string|array<string, int|string>|null, row: int|null, column: int|null}
     */
    public function normalize(): array
    {
        return ['gap' => $this->gap?->normalize(), 'row' => $this->row, 'column' => $this->column];
    }
}
