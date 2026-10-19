<?php

declare(strict_types=1);

namespace Gcego\Navigation\Contracts;

use Gcego\Navigation\Breadcrumbs\Breadcrumb;
use Gcego\Navigation\Resolution\NavigationContext;

/**
 * Breadcrumbs after the active item, for pages below it (a record, its edit page):
 *
 *     return array_filter([new Breadcrumb($context->record(Subject::class, $context->parameter('subject')) ?? '')]);
 *
 * Use $context->record() for record labels: it authorizes the record and
 * returns null for records the user may not see.
 */
interface BreadcrumbProvider
{
    /**
     * @return iterable<Breadcrumb>
     */
    public function breadcrumbs(NavigationContext $context): iterable;
}
