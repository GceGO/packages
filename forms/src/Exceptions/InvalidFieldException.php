<?php

declare(strict_types=1);

namespace Gcego\Forms\Exceptions;

use LogicException;

/**
 * A developer-authored form or field definition cannot work as declared.
 */
final class InvalidFieldException extends LogicException implements FormException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
