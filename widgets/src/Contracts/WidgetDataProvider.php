<?php

declare(strict_types=1);

namespace Gcego\Widgets\Contracts;

use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResult;

/**
 * Trusted application code that produces a widget's data:
 *
 *     final class StudentEnrolmentProvider implements WidgetDataProvider
 *     {
 *         public function resolve(WidgetDataContext $context): WidgetDataResult
 *         {
 *             $result = $context->aggregate(Enrolment::class, new Aggregation(dateColumn: 'enrolled_at', bucket: TimeBucket::Month));
 *
 *             return WidgetDataResult::series('Enrolments', $result->pairs());
 *         }
 *     }
 *
 * Providers are resolved from the container (dependency injection) and only
 * ever named by widget definitions, never by requests. Query through the
 * context's helpers (gcego/data, as the current user, scoped to the tenant)
 * so data never bypasses authorization; a provider that queries directly
 * must apply those scopes itself.
 */
interface WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult;
}
