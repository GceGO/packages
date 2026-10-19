<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests\Fixtures\Scopes;

use Gcego\Analytics\Contracts\AnalyticsScope;
use Gcego\Analytics\Security\AnalyticsContext;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Students see only their own attempts; staff see their institution's.
 */
final class StudentsSeeTheirOwn implements AnalyticsScope
{
    public function apply(EloquentBuilder|QueryBuilder $query, AnalyticsContext $context): void
    {
        $user = $context->user;

        if ($user instanceof Model && is_string($name = $user->getAttribute('name')) && str_starts_with($name, 'Student')) {
            $query->where('user_id', $user->getAuthIdentifier());
        }
    }
}
