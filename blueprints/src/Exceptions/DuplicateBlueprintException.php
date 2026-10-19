<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a BlueprintCollection receives two different blueprints with the same name.
 */
class DuplicateBlueprintException extends InvalidArgumentException implements BlueprintException
{
    public static function forName(string $name, string $existingClass, string $newClass): self
    {
        return new self(sprintf(
            'A blueprint named "%s" (%s) is already in the collection; %s cannot replace it.',
            $name,
            $existingClass,
            $newClass,
        ));
    }
}
