<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Gcego\Cli\ExitCode;
use RuntimeException;

/**
 * A path outside the application, through a symbolic link, or otherwise unsafe to write.
 */
final class UnsafePathException extends RuntimeException implements CliException
{
    public static function because(string $path, string $reason): self
    {
        return new self(sprintf('Refusing to write %s: %s', $path, $reason));
    }

    public function exitCode(): int
    {
        return ExitCode::InvalidInput;
    }
}
