<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Gcego\Cli\ExitCode;
use RuntimeException;

final class StubException extends RuntimeException implements CliException
{
    public static function because(string $stub, string $reason): self
    {
        return new self(sprintf('The stub "%s" %s', $stub, $reason));
    }

    public function exitCode(): int
    {
        return ExitCode::Failure;
    }
}
