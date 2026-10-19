<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use OutOfBoundsException;

class UnknownBlueprintException extends OutOfBoundsException implements BlueprintException
{
    /**
     * @param  list<string>  $known
     */
    public static function forName(string $name, array $known): self
    {
        return new self(sprintf(
            'No blueprint named "%s". Known blueprints: %s.',
            $name,
            $known === [] ? 'none' : implode(', ', $known),
        ));
    }
}
