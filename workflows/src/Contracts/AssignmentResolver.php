<?php

declare(strict_types=1);

namespace Gcego\Workflows\Contracts;

use Gcego\Workflows\Instances\WorkflowContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Who a task or approval goes to (reviewers, escalation targets): trusted
 * code returning user models. gcego/workflows still checks each one: they
 * must belong to the instance's tenant and satisfy the step's requirements,
 * or they are left out.
 */
interface AssignmentResolver
{
    /**
     * @return iterable<Model>
     */
    public function resolve(WorkflowContext $context): iterable;
}
