<?php

declare(strict_types=1);

namespace Gcego\Chroma\Exceptions;

use InvalidArgumentException;

class InvalidClassException extends InvalidArgumentException implements ChromaException
{
    public static function type(string $type): self
    {
        return new self(sprintf('Tailwind classes must be strings or lists of strings, %s given.', $type));
    }

    public static function malformed(string $class, string $reason): self
    {
        return new self(sprintf('"%s" is not a valid class: %s', mb_substr($class, 0, 80), $reason));
    }
}
