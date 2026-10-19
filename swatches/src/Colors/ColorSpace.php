<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

/**
 * The space a Color's coordinates are stored in.
 *
 * Srgb:  r, g, b in 0–1 (gamma encoded; may leave 0–1 when converted from a wider color).
 * Hsl:   h in degrees, s and l in 0–1.
 * Oklab: L in 0–1, a and b roughly −0.4–0.4.
 * Oklch: L in 0–1, C ≥ 0 (≈0.4 max in practice), h in degrees.
 */
enum ColorSpace: string
{
    case Srgb = 'srgb';
    case Hsl = 'hsl';
    case Oklab = 'oklab';
    case Oklch = 'oklch';
}
