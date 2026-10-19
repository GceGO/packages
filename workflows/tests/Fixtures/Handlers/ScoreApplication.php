<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;

final class ScoreApplication implements WorkflowHandler
{
    public function handle(WorkflowContext $context): ?string
    {
        Recorder::record('ScoreApplication', $context);

        return null;
    }
}
