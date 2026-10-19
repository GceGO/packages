<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Exceptions;

/**
 * The luminaries configuration (or a programmatic registration) is invalid.
 */
final class InvalidConfigurationException extends LuminariesException
{
    public static function invalid(string $key, string $reason): self
    {
        return new self(self::header().sprintf('Invalid configuration "luminaries.%s": %s', $key, $reason));
    }
}
