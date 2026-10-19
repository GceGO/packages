<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

/**
 * A verified membership: this user may act for this tenant, with these
 * tenant-specific roles and permissions. Built by the application's
 * TenantResolver from its own records, fresh for every request.
 */
final readonly class TenantMembership
{
    /**
     * @param  list<string>  $roles  Roles within this tenant only.
     * @param  list<string>  $permissions  Permissions within this tenant only; "*" suffix wildcards allowed ("members.*").
     */
    public function __construct(
        public Tenant $tenant,
        public string|int $userKey,
        public array $roles = [],
        public array $permissions = [],
    ) {}

    public function grants(string $permission): bool
    {
        foreach ($this->permissions as $granted) {
            if ($granted === $permission || (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
