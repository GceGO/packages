<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use InvalidArgumentException;

/**
 * A logo that cannot be read safely: missing, too large, an unsupported type or a malformed image.
 * Messages never contain the file's contents.
 */
class InvalidLogoException extends InvalidArgumentException implements SwatchesException
{
    public static function unreadable(): self
    {
        return new self('The logo is not a readable local file.');
    }

    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self(sprintf('The logo is %d bytes; the limit is %d.', $bytes, $limit));
    }

    public static function tooManyPixels(int $width, int $height, int $limit): self
    {
        return new self(sprintf('The logo is %d×%d pixels; the limit is %d pixels.', $width, $height, $limit));
    }

    public static function unsupported(string $type): self
    {
        return new self(sprintf('Logos must be PNG, JPEG, GIF, WebP or SVG; got %s.', preg_replace('/[^a-z0-9\/+.-]/i', '?', $type)));
    }

    public static function malformed(): self
    {
        return new self('The logo could not be decoded.');
    }
}
