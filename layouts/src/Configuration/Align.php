<?php

declare(strict_types=1);

namespace Gcego\Layouts\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Layouts\Responsive\ResponsiveValue;

/**
 * Alignment of the children of a grid, row or stack:
 *
 *     new Align(items: 'center', justify: 'between')
 *     new Align(items: ['default' => 'stretch', 'md' => 'center'])
 *
 * items (cross axis): start, center, end, stretch, baseline.
 * justify (main axis): start, center, end, between, around, evenly.
 *
 * Alignment never reorders children: the visual order is the source order.
 */
final readonly class Align implements ConfigurationNode
{
    public const array Items = ['start', 'center', 'end', 'stretch', 'baseline'];

    public const array Justify = ['start', 'center', 'end', 'between', 'around', 'evenly'];

    public ?ResponsiveValue $items;

    public ?ResponsiveValue $justify;

    /**
     * @param  string|array<string, string>|null  $items
     * @param  string|array<string, string>|null  $justify
     */
    public function __construct(string|array|null $items = null, string|array|null $justify = null)
    {
        $this->items = $items === null ? null : ResponsiveValue::of($items, static fn (mixed $value): bool => in_array($value, self::Items, true), 'Align items', 'one of '.implode(', ', self::Items));
        $this->justify = $justify === null ? null : ResponsiveValue::of($justify, static fn (mixed $value): bool => in_array($value, self::Justify, true), 'Align justify', 'one of '.implode(', ', self::Justify));

        if ($items === null && $justify === null) {
            throw InvalidLayoutException::invalid('Align', 'set items, justify or both.');
        }
    }

    public function classes(): string
    {
        return trim(($this->items?->classes(LayoutPresets::Items) ?? '').' '.($this->justify?->classes(LayoutPresets::Justify) ?? ''));
    }

    /**
     * @return array{items: int|string|array<string, int|string>|null, justify: int|string|array<string, int|string>|null}
     */
    public function normalize(): array
    {
        return ['items' => $this->items?->normalize(), 'justify' => $this->justify?->normalize()];
    }
}
