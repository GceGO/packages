<?php

declare(strict_types=1);

namespace Gcego\Data\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * An operator on the existence of related records (Eloquent sources only).
 * The filter's field is a relation path, not a column.
 */
interface RelationOperator extends Operator
{
    /**
     * @param  EloquentBuilder<Model>  $query
     * @param  Closure(EloquentBuilder<Model>): void|null  $scope  Mandatory conditions of the related records (tenant scope).
     */
    public function applyRelation(EloquentBuilder $query, string $relation, ?Closure $scope): void;
}
