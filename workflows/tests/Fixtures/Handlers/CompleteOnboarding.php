<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;

final class CompleteOnboarding implements WorkflowHandler
{
    public function handle(WorkflowContext $context): string
    {
        Recorder::record('CompleteOnboarding', $context);

        return 'finish';
    }
}
