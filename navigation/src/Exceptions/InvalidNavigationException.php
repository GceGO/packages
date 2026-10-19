<?php

declare(strict_types=1);

namespace Gcego\Navigation\Exceptions;

use InvalidArgumentException;

/**
 * A navigation definition, contribution or configuration is invalid.
 */
class InvalidNavigationException extends InvalidArgumentException implements NavigationException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
