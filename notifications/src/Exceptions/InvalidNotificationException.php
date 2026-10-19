<?php

declare(strict_types=1);

namespace Gcego\Notifications\Exceptions;

use InvalidArgumentException;

/**
 * A notification definition, builder call or configuration value is invalid.
 */
final class InvalidNotificationException extends InvalidArgumentException implements NotificationException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
