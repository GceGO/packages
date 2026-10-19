<?php

declare(strict_types=1);

namespace Gcego\Analytics\Integration\Workflows;

use Gcego\Analytics\Contracts\AnalyticsProvider;
use Gcego\Analytics\Queries\AnalyticsRows;
use Gcego\Analytics\Queries\EloquentAnalyticsExecutor;
use Gcego\Analytics\Queries\QueryPlan;
use Gcego\Analytics\Security\AnalyticsContext;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Data of the gcego/workflows tables for the workflow datasets: the
 * instances or tasks of the viewer's tenant only (the "tenant" column),
 * then the same validated aggregation as any dataset.
 */
abstract class WorkflowAnalyticsProvider implements AnalyticsProvider
{
    public function __construct(private readonly ConnectionResolverInterface $connections, private readonly EloquentAnalyticsExecutor $executor) {}

    abstract protected function table(): string;

    public function execute(QueryPlan $plan, AnalyticsContext $context): AnalyticsRows
    {
        $connection = config('gcego-workflows.execution.connection');
        $query = $this->connections->connection(is_string($connection) ? $connection : null)->table($this->table());
        $query = $context->tenant === null ? $query->whereNull('tenant') : $query->where('tenant', $context->tenant);

        return $this->executor->aggregateOn($query, $plan, $context);
    }
}
