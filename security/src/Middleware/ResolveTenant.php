<?php

declare(strict_types=1);

namespace Gcego\Security\Middleware;

use Closure;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects and verifies the tenant of a request:
 *
 *     Route::middleware(['auth', 'gcego.tenant'])->group(...);             // optional
 *     Route::middleware(['auth', 'gcego.tenant:required'])->group(...);    // a tenant must be selected
 *
 * The key is read from the route parameter (tenancy.parameter), then the
 * session (tenancy.session_key, written by switchTenant()), and verified
 * against the authenticated user's current membership on every request,
 * Livewire updates included (registered as persistent Livewire middleware).
 * A forged or foreign key is rejected (404 by default) and never trusted.
 */
final readonly class ResolveTenant
{
    public const string Alias = 'gcego.tenant';

    public function __construct(
        private TenantAuthorizer $tenants,
        private ContextFactory $contexts,
        private SecurityConfig $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $key = $this->key($request);

        if ($key === null || ! $this->config->tenancy) {
            if ($mode === 'required') {
                AuthorizationDecision::deny('tenant.access', DenialReason::TenantRequired, null, $this->config->denialStrategy)->authorize();
            }

            return $next($request);
        }

        $guard = $this->contexts->guard();
        $this->tenants->enter($request->user($guard), $key, $guard);

        return $next($request);
    }

    private function key(Request $request): ?string
    {
        $value = $request->route()?->parameter($this->config->tenantParameter);

        if ($value instanceof UrlRoutable) {
            $value = $value->getRouteKey();
        }

        if ($value === null && $request->hasSession()) {
            $value = $request->session()->get($this->config->tenantSessionKey);
        }

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
