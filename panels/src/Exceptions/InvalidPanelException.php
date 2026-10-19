<?php

declare(strict_types=1);

namespace Gcego\Panels\Exceptions;

use InvalidArgumentException;

/**
 * A panel definition, panel reference or panels configuration value is invalid
 * (also: two panels claiming the same path and domain).
 */
final class InvalidPanelException extends InvalidArgumentException implements PanelException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
