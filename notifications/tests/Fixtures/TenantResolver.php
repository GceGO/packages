<?php

declare(strict_types=1);

namespace Gcego\Notifications\Tests\Fixtures;

use Gcego\Notifications\Tests\Fixtures\Models\Membership;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;

final class TenantResolver implements Contract
{
    public function find(string $key): ?Tenant
    {
        return in_array($key, ['1', '2'], true) ? new Tenant('institution', (int) $key) : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        return Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->exists()
            ? new TenantMembership($tenant, AuthorizationContext::keyOf($user), ['member'], [])
            : null;
    }
}
