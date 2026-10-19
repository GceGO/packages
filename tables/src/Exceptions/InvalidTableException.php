<?php

declare(strict_types=1);

namespace Gcego\Tables\Exceptions;

use LogicException;

/**
 * A developer definition error: a column, filter, table or configuration that cannot work.
 */
final class InvalidTableException extends LogicException implements TableException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
