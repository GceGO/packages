<?php

declare(strict_types=1);

namespace Gcego\Actions\Exceptions;

use LogicException;

/**
 * A developer-authored action definition cannot work as declared.
 */
final class InvalidActionException extends LogicException implements ActionException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('Action %s is invalid: %s', $subject, $reason));
    }
}
