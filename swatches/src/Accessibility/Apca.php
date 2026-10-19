<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Tokens\ContrastRequirement;

/**
 * APCA lightness contrast (Lc), the perceptual contrast method proposed for WCAG 3.
 *
 * Implements APCA-W3 0.1.9 with the 0.0.98G-4g constants: sRGB screen luminance with a
 * 2.4 power, a soft black clamp, and different exponents for dark-on-light (positive Lc)
 * and light-on-dark (negative Lc) polarity. Translucent colors are composited first, as
 * in Contrast::ratio(). WCAG 2.2 AA (Contrast) remains the conformance target: APCA is
 * reported alongside it as advisory guidance.
 *
 * Reference values: #888 text on #fff is Lc 63.06; #fff text on #888 is Lc −68.54;
 * #000 on #fff is Lc 106.04.
 */
final class Apca
{
    private const float NormBackground = 0.56;

    private const float NormText = 0.57;

    private const float ReverseText = 0.62;

    private const float ReverseBackground = 0.65;

    private const float BlackThreshold = 0.022;

    private const float BlackClamp = 1.414;

    private const float Scale = 1.14;

    private const float LowOffset = 0.027;

    private const float DeltaYMin = 0.0005;

    private const float LowClip = 0.1;

    /**
     * Signed Lc: positive for dark text on a light background, negative for light on dark.
     */
    public static function contrast(Color|string $text, Color|string $background, Color|string|null $backdrop = null): float
    {
        $behind = self::color($background)->over($backdrop === null ? Color::white() : self::color($backdrop));
        $front = self::color($text)->over($behind);

        $textY = self::clampBlack(self::screenLuminance($front));
        $backgroundY = self::clampBlack(self::screenLuminance($behind));

        if (abs($backgroundY - $textY) < self::DeltaYMin) {
            return 0.0;
        }

        if ($backgroundY > $textY) {
            $sapc = ($backgroundY ** self::NormBackground - $textY ** self::NormText) * self::Scale;

            return $sapc < self::LowClip ? 0.0 : ($sapc - self::LowOffset) * 100;
        }

        $sapc = ($backgroundY ** self::ReverseBackground - $textY ** self::ReverseText) * self::Scale;

        return $sapc > -self::LowClip ? 0.0 : ($sapc + self::LowOffset) * 100;
    }

    /**
     * The |Lc| this package treats as the APCA counterpart of a WCAG requirement (APCA
     * "bronze" levels): 60 for text (75 is preferred for long body text), 45 for large text
     * and headings, 30 for non-text UI such as borders, focus rings and chart marks.
     */
    public static function minimum(ContrastRequirement $requirement): float
    {
        return match ($requirement) {
            ContrastRequirement::Text => 60.0,
            ContrastRequirement::LargeText => 45.0,
            ContrastRequirement::NonText => 30.0,
        };
    }

    public static function passes(Color|string $text, Color|string $background, ContrastRequirement $requirement = ContrastRequirement::Text, Color|string|null $backdrop = null): bool
    {
        return abs(self::contrast($text, $background, $backdrop)) >= self::minimum($requirement);
    }

    /**
     * "Lc 63.1" or "Lc −68.5".
     */
    public static function format(float $lc): string
    {
        return 'Lc '.str_replace('-', '−', number_format($lc, 1));
    }

    /**
     * APCA's screen luminance: 8-bit sRGB channels with a simple 2.4 power (not the piecewise sRGB curve).
     */
    private static function screenLuminance(Color $color): float
    {
        [$r, $g, $b] = array_map(static fn (float $channel): float => round(max(0.0, min(1.0, $channel)) * 255) / 255, $color->toSrgb());

        return 0.2126729 * $r ** 2.4 + 0.7151522 * $g ** 2.4 + 0.0721750 * $b ** 2.4;
    }

    private static function clampBlack(float $luminance): float
    {
        return $luminance > self::BlackThreshold ? $luminance : $luminance + (self::BlackThreshold - $luminance) ** self::BlackClamp;
    }

    private static function color(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::parse($color);
    }
}
