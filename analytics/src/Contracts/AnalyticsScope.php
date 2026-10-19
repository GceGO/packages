<?php

declare(strict_types=1);

namespace Gcego\Analytics\Contracts;

use Gcego\Analytics\Security\AnalyticsContext;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Record-level scope of a dataset for the current viewer, applied before
 * any aggregation (a student sees only their own attempts; a teacher only
 * their classes' students). Trusted code named by the dataset definition:
 *
 *     final class OwnAttempts implements AnalyticsScope
 *     {
 *         public function apply(EloquentBuilder|QueryBuilder $query, AnalyticsContext $context): void
 *         {
 *             $query->where('user_id', $context->user?->getAuthIdentifier() ?? 0);
 *         }
 *     }
 *
 * It narrows the rows; it can never widen what gcego/data already scoped.
 */
interface AnalyticsScope
{
    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    public function apply(EloquentBuilder|QueryBuilder $query, AnalyticsContext $context): void;
}
