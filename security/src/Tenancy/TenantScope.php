<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\ScopeProvider;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The built-in tenant data scope for gcego/data sources whose model is
 * listed in gcego-security.tenancy.models (class or interface => column).
 *
 * With a tenant: only rows whose column equals the tenant key. Without one:
 * no rows at all (fail closed). The tenant comes from the verified context,
 * never from request input. Application global scopes keep applying too.
 */
final readonly class TenantScope implements ScopeProvider
{
    /**
     * @param  array<class-string, string>  $models
     */
    public function __construct(private array $models) {}

    public function column(?string $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach ($this->models as $target => $column) {
            if (is_a($model, $target, true)) {
                return $column;
            }
        }

        return null;
    }

    public function constraints(SourceDefinition $source, AuthorizationContext $context): array
    {
        $column = $this->column($source->model);

        if ($column === null) {
            return [];
        }

        $tenant = $context->tenant;

        return [new class($column, $tenant?->key) implements QueryConstraint
        {
            public function __construct(private string $column, private string|int|null $key) {}

            public function apply(EloquentBuilder|QueryBuilder|Collection $query, SourceDefinition $source): EloquentBuilder|QueryBuilder|Collection
            {
                if ($query instanceof Collection) {
                    return $this->key === null ? $query->take(0) : $query->filter(fn (mixed $item): bool => is_scalar($value = data_get($item, $this->column)) && (string) $value === (string) $this->key);
                }

                if ($this->key === null) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->where($query instanceof EloquentBuilder ? $query->qualifyColumn($this->column) : $this->column, '=', $this->key);
            }
        }];
    }
}
