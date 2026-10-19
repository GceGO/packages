<?php

declare(strict_types=1);

namespace Gcego\Workflows\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The actor may not do this (403).
 */
final class WorkflowAuthorizationException extends AccessDeniedHttpException implements WorkflowException
{
    public static function for(string $what): self
    {
        return new self(sprintf('You may not %s.', $what));
    }
}
