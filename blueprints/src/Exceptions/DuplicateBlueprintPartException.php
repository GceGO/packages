<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use LogicException;

/**
 * Thrown when a single-part lookup finds several parts for a single-cardinality role.
 */
class DuplicateBlueprintPartException extends LogicException implements BlueprintException
{
    /**
     * @param  list<string>  $paths
     */
    public static function forRole(string $blueprint, string $role, array $paths): self
    {
        return new self(sprintf(
            'Blueprint "%s" declares %d "%s" parts (%s), but the role allows only one.',
            $blueprint,
            count($paths),
            $role,
            implode(', ', $paths),
        ));
    }
}
