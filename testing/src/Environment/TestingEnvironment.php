<?php

declare(strict_types=1);

namespace Gcego\Testing\Environment;

use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Testing\Contracts\TestEnvironment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Who is acting, in which tenant, inside one simulated request.
 *
 * actingAs() follows the order the GCEGO packages' own suites use: start a
 * new request (forget scoped instances), sign the user in on the guard, then
 * enter the tenant through gcego/security, which verifies membership. A
 * user who is not a member of the tenant is refused, exactly as in a real
 * request; nothing here bypasses authorization.
 */
final readonly class TestingEnvironment implements TestEnvironment
{
    public function __construct(private Container $container) {}

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): void
    {
        $this->newRequest();

        $auth = $this->container->make('auth');

        if ($user === null) {
            $auth->forgetGuards();
        } else {
            $auth->guard($guard)->setUser($user);

            if ($guard !== null) {
                $auth->shouldUse($guard);
            }
        }

        if ($tenant !== null) {
            $this->enterTenant((string) $tenant);
        }
    }

    public function guest(): void
    {
        $this->actingAs(null);
    }

    /**
     * Enters the tenant for the current user; throws gcego/security's
     * TenantAccessException when the user is not a member.
     */
    public function enterTenant(string $tenant): void
    {
        $this->container->make(SecurityManager::class)->enterTenant($tenant);
    }

    public function leaveTenant(): void
    {
        if ($this->container->bound(TenantContext::class) || class_exists(TenantContext::class)) {
            $this->container->make(TenantContext::class)->clear();
        }
    }

    public function currentTenant(): ?string
    {
        if (! class_exists(TenantContext::class)) {
            return null;
        }

        $tenant = $this->container->make(TenantContext::class)->tenant();

        return $tenant === null ? null : (string) $tenant->key;
    }

    public function user(): ?Authenticatable
    {
        return $this->container->make('auth')->user();
    }

    /**
     * Starts a new request: per-request (scoped) services such as the
     * tenant context, decision caches and resolvers are rebuilt on next use.
     */
    public function newRequest(): void
    {
        if (method_exists($this->container, 'forgetScopedInstances')) {
            $this->container->forgetScopedInstances();
        }
    }

    public function onPage(string $uri, array $forget = []): void
    {
        $request = Request::create($uri);

        try {
            $route = $this->container->make('router')->getRoutes()->match($request);
            $request->setRouteResolver(static fn () => $route);
            $route->bind($request);
        } catch (HttpException) {
            // No route matches: only path-based behaviour applies.
        }

        $this->container->instance('request', $request);

        foreach ($forget as $service) {
            $this->container->forgetInstance($service);
        }
    }
}
