<?php

declare(strict_types=1);

namespace Gcego\Media\Exceptions;

use InvalidArgumentException;

/**
 * A media definition, collection or configuration value is invalid.
 */
final class InvalidMediaException extends InvalidArgumentException implements MediaException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
