<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Gcego\Cli\ExitCode;
use RuntimeException;

final class GenerationException extends RuntimeException implements CliException
{
    private int $exit = ExitCode::Failure;

    public static function because(string $reason, int $exitCode = ExitCode::Failure): self
    {
        $exception = new self($reason);
        $exception->exit = $exitCode;

        return $exception;
    }

    public static function missing(string $reason): self
    {
        return self::because($reason, ExitCode::MissingDependency);
    }

    public static function invalid(string $reason): self
    {
        return self::because($reason, ExitCode::InvalidInput);
    }

    public function exitCode(): int
    {
        return $this->exit;
    }
}
