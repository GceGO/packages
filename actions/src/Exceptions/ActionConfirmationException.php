<?php

declare(strict_types=1);

namespace Gcego\Actions\Exceptions;

use RuntimeException;

/**
 * A confirmation token is missing, forged, expired, already used, or bound to another action, record or user.
 */
final class ActionConfirmationException extends RuntimeException implements ActionException
{
    public static function because(string $reason): self
    {
        return new self('The confirmation is not valid: '.$reason);
    }
}
