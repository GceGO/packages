<?php

declare(strict_types=1);

namespace Gcego\Swatches\Branding;

use Gcego\Swatches\Colors\Color;

/**
 * One color found in a logo, with its share of the logo's visible pixels (or SVG paint uses).
 */
final readonly class LogoColor
{
    public const float NeutralChroma = 0.035;

    public function __construct(
        public Color $color,
        public float $share,
    ) {}

    /**
     * Grays, near-white and near-black: backgrounds and outlines rather than brand colors.
     */
    public function isNeutral(): bool
    {
        [$lightness, $chroma] = $this->color->toOklch();

        return $chroma < self::NeutralChroma || $lightness > 0.97 || $lightness < 0.12;
    }

    /**
     * @return array{color: string, share: float, neutral: bool}
     */
    public function toArray(): array
    {
        return ['color' => $this->color->toHex(), 'share' => round($this->share, 4), 'neutral' => $this->isNeutral()];
    }
}
