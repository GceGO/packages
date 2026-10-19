<?php

declare(strict_types=1);

namespace Gcego\Infolists\Exceptions;

use LogicException;

/**
 * A developer definition error: an entry, layout, infolist or configuration that cannot work.
 */
final class InvalidEntryException extends LogicException implements InfolistException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
