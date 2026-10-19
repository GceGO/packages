<?php

declare(strict_types=1);

namespace Gcego\Layouts\Exceptions;

use InvalidArgumentException;

/**
 * A layout definition, responsive value or configuration is invalid.
 */
final class InvalidLayoutException extends InvalidArgumentException implements LayoutException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
