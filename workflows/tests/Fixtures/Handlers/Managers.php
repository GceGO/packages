<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\AssignmentResolver;
use Gcego\Workflows\Instances\WorkflowContext;

final class Managers implements AssignmentResolver
{
    public function resolve(WorkflowContext $context): iterable
    {
        return People::named(['Reviewer1']);
    }
}
