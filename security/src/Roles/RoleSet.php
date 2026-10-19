<?php

declare(strict_types=1);

namespace Gcego\Security\Roles;

/**
 * A user's roles, kept apart by scope: global (platform) roles and the
 * roles of the verified membership in the active tenant. They are never
 * merged, so "teacher" in one institution is not "teacher" in another.
 *
 * Roles describe; they do not authorize. Use permissions and policies for decisions.
 */
final readonly class RoleSet
{
    /**
     * @param  list<string>  $global
     * @param  list<string>  $tenant
     */
    public function __construct(
        public array $global = [],
        public array $tenant = [],
        public ?string $tenantId = null,
    ) {}

    public function hasGlobal(string $role): bool
    {
        return in_array($role, $this->global, true);
    }

    public function hasInTenant(string $role): bool
    {
        return in_array($role, $this->tenant, true);
    }
}
