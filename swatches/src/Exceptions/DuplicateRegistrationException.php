<?php

declare(strict_types=1);

namespace Gcego\Swatches\Exceptions;

use Gcego\Swatches\Support\Safe;
use LogicException;

/**
 * A palette or theme registration that would silently replace another.
 */
class DuplicateRegistrationException extends LogicException implements SwatchesException
{
    public static function duplicate(string $kind, string $id): self
    {
        return new self(sprintf('%s %s is already registered. Pass override: true to replace it deliberately.', ucfirst($kind), Safe::quote($id)));
    }
}
