<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Contracts\WorkflowHandler;
use Gcego\Workflows\Instances\WorkflowContext;
use RuntimeException;

/**
 * Fails while Recorder::$failures > 0.
 */
final class FlakyHandler implements WorkflowHandler
{
    public function handle(WorkflowContext $context): ?string
    {
        Recorder::record('FlakyHandler', $context);

        if (Recorder::$failures > 0) {
            Recorder::$failures--;

            throw new RuntimeException('The scoring service is down.');
        }

        return null;
    }
}
