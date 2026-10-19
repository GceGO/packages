<?php

declare(strict_types=1);

namespace Gcego\Actions\Handlers;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ActionResult;

/**
 * Read-only: the record was resolved and authorized for "view"; return a view
 * intent for the viewer of the page (future gcego/infolists). Nothing is written.
 */
final class ViewRecord implements ActionHandler
{
    public function handle(ActionContext $context): ActionResult
    {
        $context->effects->withoutRefresh()->keepDialogOpen();

        return ActionResult::success($context->identity(), '', $context->record, $context->effects, ['intent' => 'view']);
    }
}
