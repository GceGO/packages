<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Support\Safe;

/**
 * A tenant's (institution's) color preferences: an optional primary color, an optional
 * accent color and an optional theme preset. Nothing else is accepted.
 *
 * Built from untrusted input with fromArray(); colors are parsed and re-serialized,
 * so a brand can never inject CSS. A Brand carries no identity and no state: resolve
 * it per request (Contracts\BrandResolver) and pass it where it is rendered.
 */
final readonly class Brand
{
    /**
     * @throws InvalidThemeException
     */
    public function __construct(
        public ?Color $primary = null,
        public ?Color $accent = null,
        public ?string $theme = null,
    ) {
        if ($theme !== null && ! Safe::isIdentifier($theme)) {
            throw InvalidThemeException::invalidName($theme);
        }
    }

    /**
     * @param  array<mixed>  $values  ['primary' => '#2563eb', 'accent' => '#f59e0b', 'theme' => 'ocean']
     *
     * @throws InvalidColorException|InvalidThemeException
     */
    public static function fromArray(array $values): self
    {
        $unknown = array_diff(array_keys($values), ['primary', 'accent', 'theme']);

        if ($unknown !== []) {
            throw InvalidThemeException::invalidConfig('brand', 'only "primary", "accent" and "theme" are allowed.');
        }

        $color = static function (mixed $value, string $key): ?Color {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_string($value)) {
                throw InvalidThemeException::invalidConfig('brand.'.$key, 'must be a color string.');
            }

            return Color::parse($value);
        };

        $theme = $values['theme'] ?? null;

        if ($theme !== null && (! is_string($theme) || $theme === '')) {
            throw InvalidThemeException::invalidConfig('brand.theme', 'must be a theme name.');
        }

        return new self($color($values['primary'] ?? null, 'primary'), $color($values['accent'] ?? null, 'accent'), $theme);
    }

    public function isEmpty(): bool
    {
        return $this->primary === null && $this->accent === null && $this->theme === null;
    }

    /**
     * A stable identity for caching compiled output (colors as hex).
     */
    public function fingerprint(): string
    {
        return hash('xxh128', implode('|', [$this->primary?->toHex() ?? '-', $this->accent?->toHex() ?? '-', $this->theme ?? '-']));
    }

    /**
     * @return array{primary: string|null, accent: string|null, theme: string|null}
     */
    public function toArray(): array
    {
        return ['primary' => $this->primary?->toHex(), 'accent' => $this->accent?->toHex(), 'theme' => $this->theme];
    }
}
