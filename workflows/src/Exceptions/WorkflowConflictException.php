<?php

declare(strict_types=1);

namespace Gcego\Workflows\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The workflow changed since the caller last saw it (stale state, a concurrent transition) (409).
 */
final class WorkflowConflictException extends ConflictHttpException implements WorkflowException
{
    public static function stale(): self
    {
        return new self((string) __('gcego-workflows::messages.errors.stale'));
    }
}
