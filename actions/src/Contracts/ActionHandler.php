<?php

declare(strict_types=1);

namespace Gcego\Actions\Contracts;

use Gcego\Actions\Execution\ActionContext;

/**
 * Trusted server-side work of an action. Resolved from the container (so it
 * may receive dependencies) and called only after authorization,
 * confirmation and validation succeeded.
 *
 * Return an ActionResult, a model (the affected record), or nothing.
 */
interface ActionHandler
{
    public function handle(ActionContext $context): mixed;
}
