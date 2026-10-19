<?php

declare(strict_types=1);

namespace Gcego\Swatches\Accessibility;

/**
 * Color-vision deficiencies that ColorVisionSimulator can approximate.
 */
enum ColorVision: string
{
    /** No L cones (red-blind); about 1% of men. */
    case Protanopia = 'protanopia';

    /** No M cones (green-blind); the most common, about 1% of men (5% with the milder deuteranomaly). */
    case Deuteranopia = 'deuteranopia';

    /** No S cones (blue-blind); rare. */
    case Tritanopia = 'tritanopia';

    /** No color vision (rod monochromacy); very rare, and the limit for grayscale printing. */
    case Achromatopsia = 'achromatopsia';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
