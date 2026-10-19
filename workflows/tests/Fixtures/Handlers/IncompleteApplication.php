<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\Condition;
use Gcego\Workflows\Instances\WorkflowContext;

final class IncompleteApplication implements Condition
{
    public function passes(WorkflowContext $context): bool
    {
        return $context->subject()?->getAttribute('complete') !== true;
    }
}
