<?php

declare(strict_types=1);

namespace Gcego\Widgets\Exceptions;

use LogicException;

/**
 * A developer definition error: a widget, data source or configuration that cannot work.
 */
final class InvalidWidgetException extends LogicException implements WidgetException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
