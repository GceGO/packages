<?php

declare(strict_types=1);

namespace Gcego\Workflows\Contracts;

use Gcego\Workflows\Instances\WorkflowContext;

/**
 * Automation (Automation): trusted code a definition names, run after the
 * transaction commits (queued by default) as the "automation" system actor,
 * in the instance's tenant. It may return the name of a transition to run
 * next (as that system actor; the transition must allow it). Handlers must
 * be idempotent: a queued run may be retried.
 */
interface WorkflowHandler
{
    public function handle(WorkflowContext $context): ?string;
}
