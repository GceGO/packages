<?php

declare(strict_types=1);

namespace Gcego\Chroma\Exceptions;

use LogicException;

/**
 * A preset, theme or component slot registration that would silently replace another.
 */
class StyleConflictException extends LogicException implements ChromaException
{
    public static function duplicate(string $kind, string $id): self
    {
        return new self(sprintf('%s "%s" is already registered. Pass override: true to replace it deliberately.', ucfirst($kind), $id));
    }
}
