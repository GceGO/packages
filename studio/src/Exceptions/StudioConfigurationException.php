<?php

declare(strict_types=1);

namespace Gcego\Studio\Exceptions;

use InvalidArgumentException;

final class StudioConfigurationException extends InvalidArgumentException implements StudioException
{
    public static function invalid(string $key, string $reason): self
    {
        return new self(sprintf('gcego-studio.%s %s', $key, $reason));
    }
}
