<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use LogicException;

/**
 * Thrown for misuse of the composition API or an inconsistent composition policy.
 */
class BlueprintCompositionException extends LogicException implements BlueprintException
{
    public static function multipleRole(string $blueprint, string $role): self
    {
        return new self(sprintf(
            'Role "%s" of blueprint "%s" allows several parts; use parts("%s") instead of part("%s").',
            $role,
            $blueprint,
            $role,
            $role,
        ));
    }

    public static function invalidPolicy(string $reason): self
    {
        return new self('Invalid blueprint composition policy: '.$reason);
    }
}
