<?php

declare(strict_types=1);

namespace Gcego\Chroma\Exceptions;

use InvalidArgumentException;

class InvalidThemeException extends InvalidArgumentException implements ChromaException
{
    public static function invalidName(string $name): self
    {
        return new self(sprintf('"%s" is not a valid theme name. Use a lowercase identifier such as "ocean".', $name));
    }

    /**
     * @param  list<string>  $known
     */
    public static function unknown(string $name, array $known): self
    {
        return new self(sprintf('Theme "%s" is not registered. Registered themes: %s.', $name, implode(', ', $known)));
    }

    public static function invalidConfig(string $key, string $reason): self
    {
        return new self(sprintf('Invalid configuration gcego-chroma.%s: %s', $key, $reason));
    }
}
