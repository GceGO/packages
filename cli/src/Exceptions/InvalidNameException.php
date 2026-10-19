<?php

declare(strict_types=1);

namespace Gcego\Cli\Exceptions;

use Gcego\Cli\ExitCode;
use InvalidArgumentException;

final class InvalidNameException extends InvalidArgumentException implements CliException
{
    public static function because(string $subject, string $value, string $rule): self
    {
        return new self(sprintf('The %s "%s" is invalid: %s', $subject, mb_strimwidth($value, 0, 80, '…'), $rule));
    }

    public function exitCode(): int
    {
        return ExitCode::InvalidInput;
    }
}
