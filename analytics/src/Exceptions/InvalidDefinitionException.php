<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exceptions;

use InvalidArgumentException;

/**
 * An analytics definition or configuration is invalid.
 */
final class InvalidDefinitionException extends InvalidArgumentException implements AnalyticsException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
