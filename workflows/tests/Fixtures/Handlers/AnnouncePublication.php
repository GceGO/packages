<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;
use RuntimeException;

final class AnnouncePublication implements WorkflowHandler
{
    public function handle(WorkflowContext $context): ?string
    {
        Recorder::record('AnnouncePublication', $context);

        if (Recorder::$failures > 0) {
            Recorder::$failures--;

            throw new RuntimeException('The announcement service is down.');
        }

        return null;
    }
}
