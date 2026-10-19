<?php

declare(strict_types=1);

namespace Gcego\Pages\Exceptions;

use InvalidArgumentException;

/**
 * A page definition, page reference or pages configuration value is invalid.
 */
final class InvalidPageException extends InvalidArgumentException implements PageException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
