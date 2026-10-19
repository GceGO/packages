<?php

declare(strict_types=1);

namespace Gcego\Swatches\Palettes;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Colors\ColorFormat;
use Gcego\Swatches\Colors\Conversions;
use Gcego\Swatches\Colors\Gamut;
use Gcego\Swatches\Exceptions\InvalidPaletteException;

/**
 * Generates an 11-step scale (50–950) from one brand color, in OKLCH.
 *
 * The algorithm is deterministic:
 *
 * 1. Lightness and relative chroma curves are the averages of Tailwind CSS v4's
 *    17 chromatic families (see Curve).
 * 2. The brand color is the anchor: it lands unchanged on the step whose curve
 *    lightness is closest to its own (a brand color outside sRGB first has its
 *    chroma reduced to the sRGB boundary; metadata "gamut_mapped" says so).
 * 3. Other steps take the curve lightness, shifted by the anchor's offset from the
 *    curve; the shift fades linearly to zero at 50 and 950, so ends stay usable.
 * 4. Chroma follows the curve, scaled so the anchor keeps its own chroma.
 * 5. Hue is held constant.
 * 6. Chroma is capped at the sRGB boundary for that lightness and hue (with a 0.5%
 *    margin so serialized values stay inside), and each step is stored as OKLCH.
 *    Reducing chroma, rather than clipping channels, keeps the hue exact.
 *
 * Generated shades are not automatically accessible for every pairing: check the
 * pairs you use with Accessibility\Contrast or gcego:swatches:contrast.
 */
final class ScaleGenerator
{
    /**
     * step => [lightness, chroma relative to the family's peak]
     *
     * @var array<int, array{float, float}>
     */
    public const array Curve = [
        50 => [0.977, 0.082],
        100 => [0.950, 0.198],
        200 => [0.905, 0.380],
        300 => [0.840, 0.628],
        400 => [0.754, 0.860],
        500 => [0.683, 0.950],
        600 => [0.598, 0.931],
        700 => [0.515, 0.814],
        800 => [0.446, 0.670],
        900 => [0.395, 0.538],
        950 => [0.278, 0.380],
    ];

    /**
     * @throws InvalidPaletteException
     */
    public function generate(string $name, Color $brand, ?string $label = null): Palette
    {
        [$lightness, $chroma, $hue] = $brand->withAlpha(1.0)->toOklch();
        $anchor = $this->anchorStep($lightness);
        $steps = array_keys(self::Curve);
        $anchorIndex = (int) array_search($anchor, $steps, true);
        $offset = $lightness - self::Curve[$anchor][0];
        $peak = $chroma / max(self::Curve[$anchor][1], 0.01);
        $colors = [];

        foreach ($steps as $index => $step) {
            if ($step === $anchor) {
                $colors[$step] = $brand->inSrgbGamut()
                    ? Color::make($brand->space, $brand->coords, 1.0, $brand->format === ColorFormat::Named ? ColorFormat::Hex : $brand->format)
                    : Color::oklch($lightness, self::maxChroma($lightness, $hue) * 0.995, $hue);

                continue;
            }

            $distance = $index < $anchorIndex ? $anchorIndex : count($steps) - 1 - $anchorIndex;
            $fade = $distance === 0 ? 0.0 : 1 - abs($index - $anchorIndex) / $distance;
            $stepLightness = max(0.0, min(1.0, self::Curve[$step][0] + $offset * $fade));
            $stepChroma = $chroma < 0.005 ? $chroma : $peak * self::Curve[$step][1];
            $colors[$step] = Color::oklch($stepLightness, min($stepChroma, self::maxChroma($stepLightness, $hue) * 0.995), $hue);
        }

        return new Palette($name, $colors, $label, array_filter([
            'source' => 'generated',
            'anchor' => (string) $anchor,
            'brand' => $brand->toCss(),
            'gamut_mapped' => $brand->inSrgbGamut() ? null : 'true',
        ], static fn (?string $value): bool => $value !== null));
    }

    /**
     * The largest chroma displayable in sRGB at this OKLCH lightness and hue.
     */
    public static function maxChroma(float $lightness, float $hue): float
    {
        if ($lightness <= 0 || $lightness >= 1) {
            return 0.0;
        }

        $low = 0.0;
        $high = 0.5;

        for ($i = 0; $i < 24; $i++) {
            $middle = ($low + $high) / 2;
            Gamut::inSrgb(Conversions::oklabToSrgb(Conversions::oklchToOklab([$lightness, $middle, $hue]))) ? $low = $middle : $high = $middle;
        }

        return $low;
    }

    /**
     * The step whose curve lightness is nearest to $lightness.
     */
    public function anchorStep(float $lightness): int
    {
        $best = 500;
        $distance = INF;

        foreach (self::Curve as $step => [$stepLightness]) {
            if (abs($stepLightness - $lightness) < $distance) {
                $distance = abs($stepLightness - $lightness);
                $best = $step;
            }
        }

        return $best;
    }
}
