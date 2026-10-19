<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Gcego\Cli\ExitCode;
use LogicException;

/**
 * An invalid or colliding custom generator, inspector, diagnostic check or stub directory.
 */
final class ExtensionException extends LogicException implements CliException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function exitCode(): int
    {
        return ExitCode::Failure;
    }
}
