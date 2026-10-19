<?php

declare(strict_types=1);

namespace Gcego\Studio\Exceptions;

use RuntimeException;

final class InstallationException extends RuntimeException implements StudioException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
