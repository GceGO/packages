<?php

declare(strict_types=1);

namespace Gcego\Swatches\Colors;

/**
 * The CSS syntax a Color serializes to by default (the syntax it was written in).
 */
enum ColorFormat: string
{
    case Hex = 'hex';
    case Rgb = 'rgb';
    case Hsl = 'hsl';
    case Oklch = 'oklch';
    case Oklab = 'oklab';
    case Named = 'named';
}
