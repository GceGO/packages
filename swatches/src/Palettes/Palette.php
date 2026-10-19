<?php

declare(strict_types=1);

namespace Gcego\Swatches\Palettes;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Exceptions\InvalidPaletteException;
use Gcego\Swatches\Support\Safe;

/**
 * A named color scale: steps 50, 100, 200 … 900, 950 (a palette may define a subset).
 *
 *     $blue->get(600)->toHex();   // "#155dfc"
 *
 * Built-in palettes mirror Tailwind CSS; application palettes are emitted as
 * --color-<name>-<step> theme variables by gcego:swatches:generate.
 */
final readonly class Palette
{
    public const array Steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * @var array<int, Color> step => color, in step order
     */
    public array $colors;

    /**
     * @param  array<int|string, Color>  $colors
     * @param  array<string, string>  $metadata  e.g. ['source' => 'tailwindcss@4.3.3'] or ['source' => 'generated', 'anchor' => '600']
     *
     * @throws InvalidPaletteException
     */
    public function __construct(
        public string $name,
        array $colors,
        public ?string $label = null,
        public array $metadata = [],
    ) {
        if (! Safe::isIdentifier($name, 40)) {
            throw InvalidPaletteException::invalidName($name);
        }

        if ($colors === []) {
            throw InvalidPaletteException::missingStep($name, 500);
        }

        $sorted = [];

        foreach ($colors as $step => $color) {
            if (! is_int($step) && ! ctype_digit($step)) {
                throw InvalidPaletteException::invalidStep($name, $step);
            }

            if (! in_array((int) $step, self::Steps, true)) {
                throw InvalidPaletteException::invalidStep($name, $step);
            }

            $sorted[(int) $step] = $color;
        }

        ksort($sorted);
        $this->colors = $sorted;
    }

    /**
     * @param  array<int|string, string>  $values  step => CSS color
     * @param  array<string, string>  $metadata
     *
     * @throws InvalidPaletteException|InvalidColorException
     */
    public static function fromStrings(string $name, array $values, ?string $label = null, array $metadata = []): self
    {
        return new self($name, array_map(Color::parse(...), $values), $label, $metadata);
    }

    public function has(int $step): bool
    {
        return isset($this->colors[$step]);
    }

    /**
     * @throws InvalidPaletteException
     */
    public function get(int $step): Color
    {
        return $this->colors[$step] ?? throw InvalidPaletteException::missingStep($this->name, $step);
    }

    /**
     * @return list<int>
     */
    public function steps(): array
    {
        return array_keys($this->colors);
    }

    public function label(): string
    {
        return $this->label ?? ucfirst(str_replace('-', ' ', $this->name));
    }

    public function isBuiltIn(): bool
    {
        return str_starts_with($this->metadata['source'] ?? '', 'tailwindcss@');
    }

    /**
     * @return array<int, string> step => CSS color
     */
    public function toArray(): array
    {
        return array_map(static fn (Color $color): string => $color->toCss(), $this->colors);
    }
}
