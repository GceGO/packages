<?php

declare(strict_types=1);

namespace Gcego\Analytics\Contracts;

use Gcego\Analytics\Queries\AnalyticsRows;
use Gcego\Analytics\Queries\QueryPlan;
use Gcego\Analytics\Security\AnalyticsContext;

/**
 * Trusted custom data for a dataset (an external service, a precomputed
 * table with its own access rules), named by the Dataset definition, never
 * by requests. It receives a validated plan (whitelisted fields,
 * dimensions, filters and limits) and must apply the viewer's tenant and
 * access itself; results beyond $plan->limit are cut.
 */
interface AnalyticsProvider
{
    public function execute(QueryPlan $plan, AnalyticsContext $context): AnalyticsRows;
}
