<?php

declare(strict_types=1);

namespace Gcego\Vault\Tenancy;

use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Wraps the application's TenantResolver: the application still decides
 * who is a member of which tenant; Vault only adds the roles and
 * permissions assigned in that tenant to a membership the application
 * verified. A user who is not a member gets no membership, so Vault
 * assignments can never open a tenant by themselves, and assignments of
 * one tenant never appear in another.
 *
 * Read fresh on every call (TenantResolver contract): a revocation applies
 * to the next membership check. Tenant memberships are resolved before a
 * panel is known, so panel-limited roles do not add to them.
 */
final readonly class VaultTenantResolver implements TenantResolver
{
    public function __construct(private TenantResolver $inner, private Container $container) {}

    public function inner(): TenantResolver
    {
        return $this->inner;
    }

    public function find(string $key): ?Tenant
    {
        return $this->inner->find($key);
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = $this->inner->membership($user, $tenant);

        if ($membership === null || ! $user instanceof Model || ! $this->container->make(VaultConfig::class)->enabled) {
            return $membership;
        }

        $effective = $this->container->make(PermissionEvaluator::class)->effective($user, $tenant->id());

        return new TenantMembership(
            $membership->tenant,
            $membership->userKey,
            array_values(array_unique([...$membership->roles, ...$effective->roles])),
            array_values(array_unique([...$membership->permissions, ...$effective->names()])),
        );
    }
}
