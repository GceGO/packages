<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use OutOfBoundsException;

class MissingBlueprintPartException extends OutOfBoundsException implements BlueprintException
{
    public static function forRole(string $blueprint, string $role): self
    {
        return new self(sprintf('Blueprint "%s" has no "%s" part.', $blueprint, $role));
    }
}
