<?php

declare(strict_types=1);

namespace Gcego\Workflows\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No such workflow, instance or task for the viewer (other tenants' records look missing) (404).
 */
final class WorkflowNotFoundException extends NotFoundHttpException implements WorkflowException
{
    public static function make(string $what = 'workflow'): self
    {
        return new self(sprintf('The %s was not found.', $what));
    }
}
