<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a part declares an invalid role.
 */
class InvalidBlueprintPartException extends InvalidArgumentException implements BlueprintException
{
    public static function invalidRole(string $role): self
    {
        return new self(sprintf(
            'Invalid blueprint role "%s". Roles are lowercase identifiers such as "table", "action" or "reports.chart".',
            $role,
        ));
    }
}
