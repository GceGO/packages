<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

/**
 * Color space math (CSS Color Module Level 4 reference formulas).
 *
 * sRGB transfer functions are the IEC 61966-2-1 curves; the OKLab matrices are
 * Björn Ottosson's published linear-sRGB ↔ LMS ↔ OKLab matrices, as used by the
 * CSS specification and browsers. Tests pin these against Tailwind CSS v4's
 * published OKLCH palette and known hex equivalents.
 */
final class Conversions
{
    /**
     * @param  array{float, float, float}  $rgb  gamma-encoded sRGB
     * @return array{float, float, float}
     */
    public static function srgbToLinear(array $rgb): array
    {
        return [self::toLinear($rgb[0]), self::toLinear($rgb[1]), self::toLinear($rgb[2])];
    }

    /**
     * @param  array{float, float, float}  $linear
     * @return array{float, float, float}
     */
    public static function linearToSrgb(array $linear): array
    {
        return [self::toGamma($linear[0]), self::toGamma($linear[1]), self::toGamma($linear[2])];
    }

    /**
     * @param  array{float, float, float}  $rgb  gamma-encoded sRGB
     * @return array{float, float, float} L, a, b
     */
    public static function srgbToOklab(array $rgb): array
    {
        [$r, $g, $b] = self::srgbToLinear($rgb);

        $l = self::cbrt(0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b);
        $m = self::cbrt(0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b);
        $s = self::cbrt(0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    /**
     * @param  array{float, float, float}  $lab  L, a, b
     * @return array{float, float, float} gamma-encoded sRGB (unclamped)
     */
    public static function oklabToSrgb(array $lab): array
    {
        [$lightness, $a, $b] = $lab;

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return self::linearToSrgb([
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ]);
    }

    /**
     * @param  array{float, float, float}  $lab
     * @return array{float, float, float} L, C, h (degrees, 0 for achromatic colors)
     */
    public static function oklabToOklch(array $lab): array
    {
        $chroma = sqrt($lab[1] ** 2 + $lab[2] ** 2);
        $hue = $chroma < 1.0e-7 ? 0.0 : rad2deg(atan2($lab[2], $lab[1]));

        return [$lab[0], $chroma, self::normalizeHue($hue)];
    }

    /**
     * @param  array{float, float, float}  $lch
     * @return array{float, float, float}
     */
    public static function oklchToOklab(array $lch): array
    {
        $radians = deg2rad($lch[2]);

        return [$lch[0], $lch[1] * cos($radians), $lch[1] * sin($radians)];
    }

    /**
     * @param  array{float, float, float}  $hsl  h degrees, s and l 0–1
     * @return array{float, float, float}
     */
    public static function hslToSrgb(array $hsl): array
    {
        [$hue, $saturation, $lightness] = $hsl;
        $hue = self::normalizeHue($hue);

        $channel = static function (float $n) use ($hue, $saturation, $lightness): float {
            $k = fmod($n + $hue / 30, 12);
            $a = $saturation * min($lightness, 1 - $lightness);

            return $lightness - $a * max(-1, min($k - 3, 9 - $k, 1));
        };

        return [$channel(0), $channel(8), $channel(4)];
    }

    /**
     * @param  array{float, float, float}  $rgb
     * @return array{float, float, float}
     */
    public static function srgbToHsl(array $rgb): array
    {
        [$r, $g, $b] = $rgb;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta < 1.0e-9) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $lightness === 0.0 || $lightness === 1.0 ? 0.0 : $delta / (1 - abs(2 * $lightness - 1));
        $hue = match ($max) {
            $r => fmod(($g - $b) / $delta + 6, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [self::normalizeHue($hue * 60), $saturation, $lightness];
    }

    public static function normalizeHue(float $hue): float
    {
        $hue = fmod($hue, 360);

        return $hue < 0 ? $hue + 360 : $hue;
    }

    private static function toLinear(float $channel): float
    {
        $absolute = abs($channel);
        $sign = $channel < 0 ? -1 : 1;

        return $absolute <= 0.04045 ? $channel / 12.92 : $sign * (($absolute + 0.055) / 1.055) ** 2.4;
    }

    private static function toGamma(float $channel): float
    {
        $absolute = abs($channel);
        $sign = $channel < 0 ? -1 : 1;

        return $absolute <= 0.0031308 ? $channel * 12.92 : $sign * (1.055 * $absolute ** (1 / 2.4) - 0.055);
    }

    private static function cbrt(float $value): float
    {
        return $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }
}
