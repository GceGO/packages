<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

use RuntimeException;

/**
 * Base class of every exception thrown by gcego/luminaries.
 */
abstract class LuminariesException extends RuntimeException
{
    protected static function header(): string
    {
        return "GCEGO Luminaries\n\n";
    }
}
