<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Definition\SourceDefinition;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The mandatory constraint of a relation's Source: only records related to
 * the current, authorized parent (see ParentRecords). It applies to every
 * read of the relation's records: lists, record lookups, actions and form
 * options. Without a parent there are no rows (fail closed).
 */
final readonly class ParentScope implements QueryConstraint
{
    public function __construct(public RelationReference $relation) {}

    public function apply(EloquentBuilder|QueryBuilder|Collection $query, SourceDefinition $source): EloquentBuilder|QueryBuilder|Collection
    {
        if (! $query instanceof EloquentBuilder) {
            return $query instanceof Collection ? $query->take(0) : $query->whereRaw('1 = 0');
        }

        Container::getInstance()->make(ParentRecords::class)->constrain($query, $this->relation);

        return $query;
    }
}
