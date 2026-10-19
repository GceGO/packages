<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Data\Aggregation\Aggregation;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Source;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The request a navigation is resolved for: the current user, tenant and
 * route, for badge and breadcrumb providers. Built per request; never cached
 * or serialized.
 */
final readonly class NavigationContext
{
    /**
     * @param  array<string, mixed>  $parameters  Current route parameters.
     * @param  array<string, mixed>  $query
     * @param  string  $fingerprint  A hash of the security context (user, guard, tenant, membership), for cache keys.
     */
    public function __construct(
        public string $navigation,
        public ?Authenticatable $user,
        public ?Tenant $tenant,
        public ?string $route,
        public array $parameters,
        public string $path,
        public array $query,
        public string $host,
        public string $fingerprint,
        private SecurityManager $security,
        private DataManager $data,
    ) {}

    /**
     * A current route parameter (a bound model's key, or the raw value).
     */
    public function parameter(string $name): string|int|null
    {
        $value = $this->parameters[$name] ?? null;

        if ($value instanceof Model) {
            $value = $value->getKey();
        }

        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * How many records of a model or source this user may see (one aggregate query; policies and tenant scopes apply).
     *
     * @param  class-string<Model>|Source  $source
     */
    public function count(string|Source $source): int
    {
        $value = $this->data->aggregate(is_string($source) ? new Source($source) : $source, new Aggregation, null, QueryContext::for($this->user))->value;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The label of a record the user may view, or null when it is missing,
     * out of the tenant or not authorized (never leaks its existence).
     *
     * @param  class-string<Model>|Source  $source
     */
    public function record(string|Source $source, string|int|null $key, string $attribute = 'name'): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        try {
            $record = $this->security->record(is_string($source) ? new Source($source) : $source, $key);
        } catch (Throwable) {
            return null;
        }

        $label = $record instanceof Model ? $record->getAttribute($attribute) : null;

        return is_scalar($label) && (string) $label !== '' ? (string) $label : null;
    }
}
