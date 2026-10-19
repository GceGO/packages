<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures;

use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\ScopeProvider;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * A role-specific scope: assistants only see published subjects.
 */
final class PublishedOnlyScope implements ScopeProvider
{
    public function constraints(SourceDefinition $source, AuthorizationContext $context): array
    {
        if ($source->model !== Subject::class || ! ($context->membership?->hasRole('assistant') ?? false)) {
            return [];
        }

        return [new class implements QueryConstraint
        {
            public function apply(EloquentBuilder|QueryBuilder|Collection $query, SourceDefinition $source): EloquentBuilder|QueryBuilder|Collection
            {
                return $query instanceof Collection ? $query->where('is_published', true) : $query->where('is_published', true);
            }
        }];
    }
}
