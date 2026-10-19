<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use Gcego\Swatches\Support\Safe;
use InvalidArgumentException;

class InvalidPaletteException extends InvalidArgumentException implements SwatchesException
{
    public static function invalidName(string $name): self
    {
        return new self(sprintf('%s is not a valid palette name. Use a lowercase identifier such as "brand".', Safe::quote($name)));
    }

    public static function invalidStep(string $palette, int|string $step): self
    {
        return new self(sprintf('Palette %s has an invalid step %s. Steps are 50, 100, 200 … 900, 950.', Safe::quote($palette), Safe::quote((string) $step)));
    }

    public static function missingStep(string $palette, int $step): self
    {
        return new self(sprintf('Palette %s has no step %d.', Safe::quote($palette), $step));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknown(string $name, array $known): self
    {
        return new self(sprintf('Palette %s is not registered. Registered palettes: %s.', Safe::quote($name), implode(', ', $known)));
    }
}
