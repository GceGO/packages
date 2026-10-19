<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Tokens\ContrastRequirement;

/**
 * WCAG 2.x contrast ratios, with alpha compositing.
 *
 * A translucent background is first painted over $backdrop (white unless given),
 * then a translucent foreground over that result, so the ratio describes what is
 * actually displayed. Ratios are not rounded: 4.499 fails 4.5:1, as WCAG requires.
 */
final class Contrast
{
    /**
     * @throws InvalidColorException
     */
    public static function ratio(Color|string $foreground, Color|string $background, Color|string|null $backdrop = null): float
    {
        $backdropColor = $backdrop === null ? Color::white() : self::color($backdrop);
        $behind = self::color($background)->over($backdropColor);
        $front = self::color($foreground)->over($behind);

        $lighter = max($front->luminance(), $behind->luminance());
        $darker = min($front->luminance(), $behind->luminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function passes(Color|string $foreground, Color|string $background, ContrastRequirement $requirement = ContrastRequirement::Text, Color|string|null $backdrop = null): bool
    {
        return self::ratio($foreground, $background, $backdrop) >= $requirement->minimum();
    }

    /**
     * "4.52:1", truncated (never rounded up past a threshold).
     */
    public static function format(float $ratio): string
    {
        return number_format(floor($ratio * 100) / 100, 2).':1';
    }

    /**
     * The nearest foreground (same hue and chroma, OKLCH lightness moved as little as
     * possible, darker or lighter) that reaches $minimum on $background; null if none can.
     */
    public static function suggest(Color|string $foreground, Color|string $background, float $minimum, Color|string|null $backdrop = null): ?Color
    {
        $front = self::color($foreground);
        $behind = self::color($background)->over($backdrop === null ? Color::white() : self::color($backdrop));

        if (self::ratio($front, $behind) >= $minimum) {
            return $front;
        }

        [$lightness, $chroma, $hue] = $front->toOklch();
        $best = null;

        foreach ([0.0, 1.0] as $limit) {
            $candidate = Color::oklch($limit, $chroma, $hue, $front->alpha);

            if (self::ratio($candidate, $behind) < $minimum) {
                continue;
            }

            $low = $lightness;
            $high = $limit;

            for ($i = 0; $i < 30; $i++) {
                $middle = ($low + $high) / 2;
                $passes = self::ratio(Color::oklch($middle, $chroma, $hue, $front->alpha), $behind) >= $minimum;
                $passes ? $high = $middle : $low = $middle;
            }

            // Step on until the 8-bit hex value passes too: rounding must never undo the fix.
            $direction = $limit > $lightness ? 1 : -1;
            $found = null;

            for ($step = 0; $step <= 50; $step++) {
                $candidate = Color::parse(Color::oklch($high + $direction * $step * 0.001, $chroma, $hue)->toHex());

                if (self::ratio($candidate, $behind) >= $minimum) {
                    $found = $front->isOpaque() ? $candidate : $candidate->withAlpha($front->alpha);

                    break;
                }
            }

            if ($found !== null && ($best === null || abs($high - $lightness) < $best[0])) {
                $best = [abs($high - $lightness), $found];
            }
        }

        return $best === null ? null : $best[1];
    }

    private static function color(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::parse($color);
    }
}
