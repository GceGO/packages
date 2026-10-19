<?php

declare(strict_types=1);

namespace Gcego\Testing\Exceptions;

use RuntimeException;

final class TestEnvironmentException extends RuntimeException implements TestingException
{
    public static function because(string $reason): self
    {
        return new self('The test environment is not usable: '.$reason);
    }
}
