<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The application's tenancy, behind one contract (configured as gcego-security.tenancy.resolver).
 *
 * Both methods must read current, authoritative state (no stale caches):
 * a suspended tenant or an ended membership must take effect on the next request.
 */
interface TenantResolver
{
    /**
     * The tenant with this key, or null. Finding a tenant grants nothing.
     */
    public function find(string $key): ?Tenant;

    /**
     * The user's verified membership in the tenant, or null when the user may not act for it.
     */
    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership;
}
