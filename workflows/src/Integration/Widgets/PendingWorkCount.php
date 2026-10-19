<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration\Widgets;

use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Workflows\Tasks\Inbox;
use Illuminate\Database\Eloquent\Model;

/**
 * How many approvals and tasks wait for the widget's viewer (their inbox count).
 */
final class PendingWorkCount implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        return $context->user instanceof Model ? WidgetDataResult::value(app(Inbox::class)->count($context->user)) : WidgetDataResult::empty();
    }
}
