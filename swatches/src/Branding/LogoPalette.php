<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Exceptions\InvalidThemeException;

/**
 * The colors extracted from a logo, most prominent first, and the brand they suggest.
 */
final readonly class LogoPalette
{
    /**
     * A second brand color must differ this much (ΔE OK) from the primary to be an accent.
     */
    public const float AccentDistance = 0.12;

    /**
     * @param  list<LogoColor>  $colors  most prominent first
     */
    public function __construct(
        public array $colors,
        public string $source,
    ) {}

    /**
     * @return list<LogoColor>
     */
    public function chromatic(): array
    {
        return array_values(array_filter($this->colors, static fn (LogoColor $color): bool => ! $color->isNeutral()));
    }

    /**
     * Primary: the most prominent chromatic color. Accent: the next chromatic color that is
     * clearly different from it. A logo with no chromatic color suggests no colors at all,
     * so the theme's own palette stays. The result still goes through BrandCompiler, which
     * adjusts or refuses colors that cannot be made accessible.
     *
     * @throws InvalidThemeException
     */
    public function suggestBrand(?string $theme = null): Brand
    {
        $chromatic = $this->chromatic();
        $primary = $chromatic[0] ?? null;
        $accent = null;

        foreach (array_slice($chromatic, 1) as $candidate) {
            if ($primary !== null && $candidate->color->deltaE($primary->color) >= self::AccentDistance) {
                $accent = $candidate;

                break;
            }
        }

        return new Brand($primary?->color, $accent?->color, $theme);
    }

    /**
     * @return array{source: string, colors: list<array{color: string, share: float, neutral: bool}>, suggestion: array{primary: string|null, accent: string|null, theme: string|null}}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'colors' => array_map(static fn (LogoColor $color): array => $color->toArray(), $this->colors),
            'suggestion' => $this->suggestBrand()->toArray(),
        ];
    }
}
