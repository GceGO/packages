<?php

declare(strict_types=1);

namespace Gcego\Chroma\Exceptions;

use InvalidArgumentException;

/**
 * Messages never echo the rejected value: token values end up inside <style>.
 */
class InvalidTokenException extends InvalidArgumentException implements ChromaException
{
    /**
     * @param  list<string>  $known
     */
    public static function unknown(string $name, array $known): self
    {
        return new self(sprintf('"%s" is not a GCEGO design token. Known tokens: %s.', $name, implode(', ', $known)));
    }

    public static function colorToken(string $name): self
    {
        return new self(sprintf('"%s" is a color token: colors belong to gcego/swatches. Set it in a theme of config/gcego-swatches.php instead.', $name));
    }

    public static function invalidValue(string $name, string $type): self
    {
        return new self(sprintf('The value of token "%s" is not a valid %s (it may not contain ; { } < > \\ or url()).', $name, $type));
    }
}
