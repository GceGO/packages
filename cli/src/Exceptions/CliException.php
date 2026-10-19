<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Throwable;

/**
 * Every exception gcego/cli throws; exitCode() is the command's exit code.
 */
interface CliException extends Throwable
{
    public function exitCode(): int;
}
