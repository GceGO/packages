<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Workflows;

final class WorkflowTasksProvider extends WorkflowAnalyticsProvider
{
    protected function table(): string
    {
        return 'gcego_workflow_tasks';
    }
}
