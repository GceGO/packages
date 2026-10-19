<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

use Gcego\Security\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Used while tenancy is disabled: no tenant exists, so no membership does.
 */
final class NullTenantResolver implements TenantResolver
{
    public function find(string $key): ?Tenant
    {
        return null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        return null;
    }
}
