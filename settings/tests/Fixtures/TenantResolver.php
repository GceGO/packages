<?php

declare(strict_types=1);

namespace Gcego\Settings\Tests\Fixtures;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Settings\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

final class TenantResolver implements Contract
{
    public function find(string $key): ?Tenant
    {
        return in_array($key, ['1', '2'], true) ? new Tenant('institution', (int) $key, name: $key === '1' ? 'North College' : 'South College') : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->first();

        return $membership instanceof Membership
            ? new TenantMembership($tenant, AuthorizationContext::keyOf($user), ['member'], array_values(array_filter($membership->permissions ?? [], is_string(...))))
            : null;
    }
}
