<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use Closure;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Builds the AuthorizationContext of the current request or job from
 * Laravel's guard (the source of identity) and the scoped TenantContext.
 *
 * Bound as a scoped instance. run() temporarily replaces the context (for
 * SecurityManager::actingAs() and queued operations) and always restores it.
 */
final class ContextFactory
{
    /**
     * @var list<AuthorizationContext>
     */
    private array $overrides = [];

    public function __construct(
        private readonly AuthManager $auth,
        private readonly TenantContext $tenants,
        private readonly TenantResolver $resolver,
        private readonly SecurityConfig $config,
    ) {}

    public function current(?string $guard = null): AuthorizationContext
    {
        if ($this->overrides !== [] && $guard === null) {
            return $this->overrides[array_key_last($this->overrides)];
        }

        $guard ??= $this->guard();
        $user = $this->auth->guard($guard)->user();
        $membership = $this->tenants->membership();

        // A membership is only meaningful for the user it was verified for.
        if ($membership !== null && ($user === null || (string) $membership->userKey !== (string) AuthorizationContext::keyOf($user))) {
            $membership = null;
        }

        return new AuthorizationContext($user, $guard, $this->tenants->tenant(), $membership);
    }

    /**
     * A context for another user in the current tenant, with that user's own membership verified now.
     */
    public function forUser(?Authenticatable $user, ?string $guard = null): AuthorizationContext
    {
        $tenant = $this->tenants->tenant();
        $membership = $user !== null && $tenant !== null ? $this->resolver->membership($user, $tenant) : null;

        return new AuthorizationContext($user, $guard ?? $this->guard(), $tenant, $membership);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(AuthorizationContext $context, Closure $callback): mixed
    {
        $this->overrides[] = $context;

        try {
            return $this->tenants->run($context->tenant, $context->membership, $callback);
        } finally {
            array_pop($this->overrides);
        }
    }

    public function guard(): string
    {
        return $this->config->guard ?? $this->auth->getDefaultDriver();
    }
}
