<?php

declare(strict_types=1);

namespace Gcego\Workflows\Contracts;

use Gcego\Workflows\Instances\WorkflowContext;

/**
 * Chooses a branch of a conditional transition (Branch): the first branch
 * whose condition passes decides the destination. Deterministic, trusted code.
 */
interface Condition
{
    public function passes(WorkflowContext $context): bool;
}
