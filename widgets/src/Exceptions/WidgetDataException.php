<?php

declare(strict_types=1);

namespace Gcego\Widgets\Exceptions;

use RuntimeException;

/**
 * A widget's data could not be produced or is not valid for the widget
 * (too many chart points, non-numeric values). The message is safe to log;
 * users only ever see the widget's generic error state.
 */
final class WidgetDataException extends RuntimeException implements WidgetException
{
    public static function invalid(string $widget, string $reason): self
    {
        return new self(sprintf('Widget "%s" data is invalid: %s', mb_substr($widget, 0, 128), $reason));
    }
}
