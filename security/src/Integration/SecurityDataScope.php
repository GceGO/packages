<?php

declare(strict_types=1);

namespace Gcego\Security\Integration;

use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Support\WhereGroups;
use Gcego\Security\Authorization\AuthorizerRegistry;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantScope;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Registered once in gcego/data's ScopeRegistry for every source. On each
 * execution it applies the built-in TenantScope and every registered
 * ScopeProvider for the current AuthorizationContext (the request's user and
 * tenant, or the context of SecurityManager::actingAs()).
 *
 * Because gcego/data applies it before request filters, search, sorting,
 * pagination and record lookups, none of those can remove it. Each provided
 * constraint is isolated in its own where group.
 */
final readonly class SecurityDataScope implements QueryConstraint
{
    public function __construct(private Container $container) {}

    public function apply(EloquentBuilder|QueryBuilder|Collection $query, SourceDefinition $source): EloquentBuilder|QueryBuilder|Collection
    {
        $context = $this->container->make(ContextFactory::class)->current();
        $providers = [new TenantScope($this->container->make(SecurityConfig::class)->tenantModels), ...$this->container->make(AuthorizerRegistry::class)->scopes()];

        foreach ($providers as $provider) {
            foreach ($provider->constraints($source, $context) as $constraint) {
                if ($query instanceof Collection) {
                    $result = $constraint->apply($query, $source);
                    $query = $result instanceof Collection ? $result : throw MissingAuthorizationContextException::because(sprintf('Scope %s must return the filtered collection.', $constraint::class));

                    continue;
                }

                $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
                $before = count($base->wheres);
                $result = $constraint->apply($query, $source);

                if ($result !== null && $result !== $query) {
                    throw MissingAuthorizationContextException::because(sprintf('Scope %s must modify and return the builder it receives.', $constraint::class));
                }

                WhereGroups::isolate($base, $before);
            }
        }

        return $query;
    }
}
