<?php

declare(strict_types=1);

namespace Gcego\Vault\Support;

use Gcego\Panels\PanelManager;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\Tenancy\VaultTenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The request's actor, panel and tenant, read from their owners on every
 * call (Laravel's guard via gcego/security, gcego/panels, the verified
 * TenantContext), never from request input and never kept.
 */
final readonly class Context
{
    public function __construct(private Container $container) {}

    public function actor(): ?Model
    {
        try {
            $user = $this->container->make(ContextFactory::class)->current()->user;
        } catch (Throwable) {
            return null;
        }

        return $user instanceof Model ? $user : null;
    }

    public function panel(): ?string
    {
        try {
            return $this->container->bound(PanelManager::class) ? $this->container->make(PanelManager::class)->current()?->name() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The verified tenant of this request, as a scope ("" without one).
     */
    public function tenant(): string
    {
        return Scope::of($this->container->make(TenantContext::class)->tenant());
    }

    /**
     * The tenant behind a stored scope, found through the application's TenantResolver (null: none or global).
     */
    public function tenantOf(string $scope): ?Tenant
    {
        if ($scope === Scope::Global || ! str_contains($scope, ':')) {
            return null;
        }

        $tenant = $this->resolver()->find(substr($scope, strpos($scope, ':') + 1));

        return $tenant !== null && $tenant->id() === $scope ? $tenant : null;
    }

    /**
     * Whether the user is a verified member of the tenant (always true globally). Read fresh.
     */
    public function isMember(Authenticatable $user, string $scope): bool
    {
        if ($scope === Scope::Global) {
            return true;
        }

        $tenant = $this->tenantOf($scope);

        return $tenant !== null && $this->resolver()->membership($user, $tenant) !== null;
    }

    private function resolver(): TenantResolver
    {
        $resolver = $this->container->make(TenantResolver::class);

        return $resolver instanceof VaultTenantResolver ? $resolver->inner() : $resolver;
    }
}
