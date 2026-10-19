<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\TransitionGuard;
use Gcego\Workflows\Engine\GuardResult;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Models\WorkflowInstance;

/**
 * Simulates another process committing a change to the instance while this
 * transition is running (the row's version moves under it).
 */
final class ConcurrentWriter implements TransitionGuard
{
    public function check(WorkflowContext $context): GuardResult|bool
    {
        WorkflowInstance::query()->whereKey($context->instance->id)->increment('lock_version');

        return true;
    }
}
