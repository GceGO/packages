<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

/**
 * sRGB gamut mapping: the CSS Color Module Level 4 algorithm (§13.2).
 *
 * An out-of-gamut color keeps its OKLCH lightness and hue while chroma is
 * reduced by binary search, until clipping the candidate is within one just
 * noticeable difference (ΔE OK 0.02) of it. Lightness ≥ 1 maps to white and
 * ≤ 0 to black.
 */
final class Gamut
{
    private const float JustNoticeable = 0.02;

    private const float Epsilon = 0.0001;

    private const float Tolerance = 0.000_01;

    /**
     * @param  array{float, float, float}  $rgb
     */
    public static function inSrgb(array $rgb): bool
    {
        foreach ($rgb as $channel) {
            if ($channel < -self::Tolerance || $channel > 1 + self::Tolerance) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{float, float, float}  $lch  OKLCH
     * @return array{float, float, float} in-gamut sRGB, channels 0–1
     */
    public static function mapOklch(array $lch): array
    {
        [$lightness, $chroma, $hue] = $lch;

        if ($lightness >= 1) {
            return [1.0, 1.0, 1.0];
        }

        if ($lightness <= 0) {
            return [0.0, 0.0, 0.0];
        }

        $origin = Conversions::oklabToSrgb(Conversions::oklchToOklab($lch));

        if (self::inSrgb($origin)) {
            return self::clip($origin);
        }

        $clipped = self::clip($origin);

        if (self::deltaE($clipped, $lch) < self::JustNoticeable) {
            return $clipped;
        }

        $min = 0.0;
        $max = $chroma;
        $minInGamut = true;

        while ($max - $min > self::Epsilon) {
            $candidate = [$lightness, ($min + $max) / 2, $hue];
            $rgb = Conversions::oklabToSrgb(Conversions::oklchToOklab($candidate));

            if ($minInGamut && self::inSrgb($rgb)) {
                $min = $candidate[1];

                continue;
            }

            $clipped = self::clip($rgb);
            $difference = self::deltaE($clipped, $candidate);

            if ($difference < self::JustNoticeable) {
                if (self::JustNoticeable - $difference < self::Epsilon) {
                    return $clipped;
                }

                $minInGamut = false;
                $min = $candidate[1];
            } else {
                $max = $candidate[1];
            }
        }

        return $clipped;
    }

    /**
     * @param  array{float, float, float}  $rgb
     * @return array{float, float, float}
     */
    public static function clip(array $rgb): array
    {
        return [max(0.0, min(1.0, $rgb[0])), max(0.0, min(1.0, $rgb[1])), max(0.0, min(1.0, $rgb[2]))];
    }

    /**
     * ΔE OK between an sRGB color and an OKLCH color.
     *
     * @param  array{float, float, float}  $rgb
     * @param  array{float, float, float}  $lch
     */
    private static function deltaE(array $rgb, array $lch): float
    {
        $a = Conversions::srgbToOklab($rgb);
        $b = Conversions::oklchToOklab($lch);

        return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
    }
}
