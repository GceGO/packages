<?php

declare(strict_types=1);

namespace Gcego\Modules\Relationships;

use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\Support\CurrentPageRoute;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Records\RecordAuthorizer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Routing\Route;

/**
 * The parent record of a relation page, for the current request (scoped).
 *
 * The key comes from the {parent} parameter of the current route when that
 * route is one of the relation's pages, or, for a Livewire update from a
 * component on such a page (its table, form or action host), from that
 * page's URL as recorded in the component snapshot, which Livewire
 * protects with a checksum. Nothing else can name a parent.
 *
 * A key proves nothing: the parent is looked up through the parent
 * resource's Source (tenant and security scopes; 404 out of scope) and the
 * ability is authorized on it (view to read, update to change the
 * relationship) by gcego/security. Decisions are memoized for the request.
 */
final class ParentRecords
{
    /**
     * @var array<string, array{0: AuthorizationDecision, 1: ?Model}>
     */
    private array $decisions = [];

    public function __construct(private readonly Container $container) {}

    /**
     * The (unverified) parent key of the current request, or null outside the relation's pages.
     */
    public function key(RelationReference $relation): ?string
    {
        $route = $this->currentRoute();

        if ($route === null) {
            return null;
        }

        $page = $route->getAction(PageRouteRegistrar::ActionKey);

        if (! is_string($page) || ! $relation->ownsPage($page)) {
            return null;
        }

        $key = $route->parameter(RelationDefinition::ParentParameter);

        return is_string($key) && $key !== '' && strlen($key) <= RecordKey::MaxLength ? $key : (is_int($key) ? (string) $key : null);
    }

    /**
     * The decision on the parent for $ability, and the parent when allowed.
     *
     * @return array{0: AuthorizationDecision, 1: ?Model}
     */
    public function inspect(RelationReference $relation, string $ability = 'view'): array
    {
        $key = $this->key($relation);

        if ($key === null) {
            return [AuthorizationDecision::deny('modules.parent', DenialReason::NotFound, null, DenialStrategy::Conceal), null];
        }

        $memo = $relation->identity().'|'.$ability.'|'.$key;

        if (isset($this->decisions[$memo])) {
            return $this->decisions[$memo];
        }

        [$decision, $record] = $this->container->make(RecordAuthorizer::class)->inspect($relation->parentSource, $key, $ability);

        return $this->decisions[$memo] = [$decision, $decision->allowed && $record instanceof Model ? $record : null];
    }

    /**
     * The authorized parent.
     *
     * @throws AccessDeniedException (404 when missing or out of scope, 403 when denied)
     */
    public function resolve(RelationReference $relation, string $ability = 'view'): Model
    {
        [$decision, $parent] = $this->inspect($relation, $ability);

        if ($parent === null) {
            throw AccessDeniedException::fromDecision($decision->allowed ? AuthorizationDecision::deny('modules.parent', DenialReason::NotFound, null, DenialStrategy::Conceal) : $decision);
        }

        return $parent;
    }

    /**
     * The parent's relationship (the method declared by the Relation).
     *
     * @return EloquentRelation<Model, Model, mixed>
     */
    public function relationship(Model $parent, RelationReference $relation): EloquentRelation
    {
        $method = $relation->relationship;

        if (! method_exists($parent, $method)) {
            throw InvalidModuleException::invalid('relation '.$relation->identity(), sprintf('%s has no "%s" relationship.', $parent::class, $method));
        }

        $instance = $parent->{$method}();

        if (! $instance instanceof EloquentRelation) {
            throw InvalidModuleException::invalid('relation '.$relation->identity(), sprintf('%s::%s() does not return an Eloquent relationship.', $parent::class, $method));
        }

        if ($relation->model !== null && ! $instance->getRelated() instanceof $relation->model) {
            throw InvalidModuleException::invalid('relation '.$relation->identity(), sprintf('%s::%s() relates %s, not %s.', $parent::class, $method, $instance->getRelated()::class, $relation->model));
        }

        return $instance;
    }

    /**
     * Restrict a query of related records to the authorized parent's
     * relationship (no parent: no rows).
     *
     * @param  Builder<Model>  $query
     */
    public function constrain(Builder $query, RelationReference $relation): void
    {
        if ($this->key($relation) === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $parent = $this->resolve($relation, 'view');
        $relationship = $this->relationship($parent, $relation);
        $related = $relationship->getRelated();

        if (! $query->getModel() instanceof $related) {
            throw InvalidModuleException::invalid('relation '.$relation->identity(), sprintf('its records are %s, but the relationship relates %s.', $query->getModel()::class, $related::class));
        }

        $query->whereIn($query->getModel()->getQualifiedKeyName(), $relationship->getQuery()->toBase()->select($related->getQualifiedKeyName()));
    }

    /**
     * Forget memoized decisions (after the parent changed in this request).
     */
    public function forget(): void
    {
        $this->decisions = [];
    }

    private function currentRoute(): ?Route
    {
        return $this->container->make(CurrentPageRoute::class)->route();
    }
}
