<?php

declare(strict_types=1);

namespace Gcego\Security\Exceptions;

use LogicException;

/**
 * An operation needs context that is not available: an inconsistent
 * user/tenant/membership combination, an operation issued for another
 * user, or invalid security configuration.
 */
final class MissingAuthorizationContextException extends LogicException implements SecurityException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
