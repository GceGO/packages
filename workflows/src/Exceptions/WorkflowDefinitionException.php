<?php

declare(strict_types=1);

namespace Gcego\Workflows\Exceptions;

use InvalidArgumentException;

/**
 * A workflow definition or configuration is invalid, unknown, or incompatible with a running instance.
 */
final class WorkflowDefinitionException extends InvalidArgumentException implements WorkflowException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }
}
