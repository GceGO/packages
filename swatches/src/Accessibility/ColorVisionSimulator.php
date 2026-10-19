<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Colors\ColorSpace;
use Gcego\Swatches\Colors\Conversions;

/**
 * Approximates how colors look with a color-vision deficiency.
 *
 * Dichromacies use the Machado, Oliveira and Fernandes (2009) matrices for full severity,
 * applied to linear sRGB; $severity below 1 interpolates towards normal vision, a common
 * approximation of the anomalous trichromacies. Achromatopsia maps to the color's WCAG
 * luminance. A simulation helps catch palettes that collapse (red/green status colors,
 * neighbouring chart series); it is not a substitute for testing with people.
 */
final class ColorVisionSimulator
{
    /**
     * @var array<string, array{array{float, float, float}, array{float, float, float}, array{float, float, float}}>
     */
    private const array Matrices = [
        'protanopia' => [[0.152286, 1.052583, -0.204868], [0.114503, 0.786281, 0.099216], [-0.003882, -0.048116, 1.051998]],
        'deuteranopia' => [[0.367322, 0.860646, -0.227968], [0.280085, 0.672501, 0.047413], [-0.011820, 0.042940, 0.968881]],
        'tritanopia' => [[1.255528, -0.076749, -0.178779], [-0.078411, 0.930809, 0.147602], [0.004733, 0.691367, 0.303900]],
    ];

    public static function simulate(Color $color, ColorVision $vision, float $severity = 1.0): Color
    {
        $severity = max(0.0, min(1.0, $severity));
        $linear = Conversions::srgbToLinear($color->toSrgb());

        if ($vision === ColorVision::Achromatopsia) {
            $gray = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
            $simulated = [$gray, $gray, $gray];
        } else {
            $matrix = self::Matrices[$vision->value];
            $simulated = [
                $matrix[0][0] * $linear[0] + $matrix[0][1] * $linear[1] + $matrix[0][2] * $linear[2],
                $matrix[1][0] * $linear[0] + $matrix[1][1] * $linear[1] + $matrix[1][2] * $linear[2],
                $matrix[2][0] * $linear[0] + $matrix[2][1] * $linear[1] + $matrix[2][2] * $linear[2],
            ];
        }

        $mixed = [
            $linear[0] + ($simulated[0] - $linear[0]) * $severity,
            $linear[1] + ($simulated[1] - $linear[1]) * $severity,
            $linear[2] + ($simulated[2] - $linear[2]) * $severity,
        ];
        $srgb = Conversions::linearToSrgb([max(0.0, min(1.0, $mixed[0])), max(0.0, min(1.0, $mixed[1])), max(0.0, min(1.0, $mixed[2]))]);

        return Color::make(ColorSpace::Srgb, $srgb, $color->alpha);
    }

    /**
     * The smallest ΔE OK between any two colors once simulated, and which pair it is.
     *
     * @param  array<string, Color>  $colors  name => color
     * @return array{distance: float, pair: array{string, string}|null}
     */
    public static function closestPair(array $colors, ColorVision $vision): array
    {
        $simulated = array_map(static fn (Color $color): Color => self::simulate($color, $vision), $colors);
        $names = array_keys($simulated);
        $best = ['distance' => INF, 'pair' => null];

        foreach ($names as $i => $first) {
            foreach (array_slice($names, $i + 1) as $second) {
                $distance = $simulated[$first]->deltaE($simulated[$second]);

                if ($distance < $best['distance']) {
                    $best = ['distance' => $distance, 'pair' => [(string) $first, (string) $second]];
                }
            }
        }

        return $best;
    }
}
