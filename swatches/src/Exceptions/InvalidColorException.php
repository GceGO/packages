<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use Gcego\Swatches\Support\Safe;
use InvalidArgumentException;

/**
 * A value that is not a color Swatches can parse. Messages quote input only through Safe::quote().
 */
class InvalidColorException extends InvalidArgumentException implements SwatchesException
{
    public static function unparsable(string $value): self
    {
        return new self(sprintf('%s is not a supported color. Use hex, rgb(), hsl(), oklch(), oklab() or a CSS color name.', Safe::quote($value)));
    }

    public static function unresolved(string $value): self
    {
        return new self(sprintf('%s is a reference, not a color: resolve it against a token set first.', Safe::quote($value)));
    }

    public static function outOfRange(string $channel, string $value): self
    {
        return new self(sprintf('The %s of %s is out of range.', $channel, Safe::quote($value)));
    }
}
