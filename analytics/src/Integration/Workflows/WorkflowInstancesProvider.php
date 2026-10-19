<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Workflows;

final class WorkflowInstancesProvider extends WorkflowAnalyticsProvider
{
    protected function table(): string
    {
        return 'gcego_workflow_instances';
    }
}
