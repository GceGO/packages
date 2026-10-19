<?php

declare(strict_types=1);

namespace Gcego\Vault\Concerns;

use DateTimeInterface;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Vault\Assignments\AssignmentManager;
use Gcego\Vault\Authorization\EffectivePermissions;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Vault;
use Illuminate\Database\Eloquent\Builder;

/**
 * Roles and permissions on a model (usually the User):
 *
 *     $user->assignRole('teacher');                          // global
 *     $user->assignRole('teacher', 'institution:7', now()->addMonth());
 *     $user->removeRole('teacher');
 *     $user->holdsRole('teacher');
 *     $user->hasPermissionTo('subjects.update');
 *     $user->hasPermissionTo('grades.update', $tenant);
 *     $user->givePermissionTo('reports.export');
 *
 * The writing methods are TRUSTED application code (seeders, console,
 * domain logic that is itself the authority): they apply Vault's structural
 * rules but not the actor rules. For changes a person asks for, use the
 * governed API, which checks the actor's authority and every escalation
 * rule: Vault::assignments()->assignRole($actor, $user, 'teacher').
 *
 * holdsRole() is named so it never collides with an application's own
 * hasRole() (for example one reading an enum column).
 */
trait HasVaultAccess
{
    public function assignRole(Role|string $role, Tenant|string|null $tenant = null, ?DateTimeInterface $until = null, ?string $reason = null): Assignment
    {
        return app(AssignmentManager::class)->assignTrusted($this, $role, Vault::scope($tenant), $until, $reason);
    }

    public function removeRole(Role|string $role, Tenant|string|null $tenant = null, ?string $reason = null): bool
    {
        return app(AssignmentManager::class)->revokeRoleTrusted($this, $role, Vault::scope($tenant), $reason);
    }

    public function givePermissionTo(Permission|string $permission, Tenant|string|null $tenant = null, ?DateTimeInterface $until = null, ?string $reason = null): Grant
    {
        return app(AssignmentManager::class)->grantTrusted($this, $permission, Vault::scope($tenant), $until, $reason);
    }

    public function revokePermissionTo(Permission|string $permission, Tenant|string|null $tenant = null, ?string $reason = null): bool
    {
        return app(AssignmentManager::class)->revokePermissionTrusted($this, $permission, Vault::scope($tenant), $reason);
    }

    public function holdsRole(string $role, Tenant|string|null $tenant = null): bool
    {
        return $this->effectivePermissions($tenant)->holdsRole($role);
    }

    public function hasPermissionTo(string $permission, Tenant|string|null $tenant = null): bool
    {
        return app(Vault::class)->allows($this, $permission, $tenant);
    }

    /**
     * @return list<string>
     */
    public function vaultRoles(Tenant|string|null $tenant = null): array
    {
        return $this->effectivePermissions($tenant)->roles;
    }

    public function effectivePermissions(Tenant|string|null $tenant = null): EffectivePermissions
    {
        return app(Vault::class)->effective($this, $tenant);
    }

    /**
     * Every role assignment of this model, ended ones included (history).
     *
     * @return Builder<Assignment>
     */
    public function vaultAssignments(): Builder
    {
        $holder = Holder::of($this);

        return Assignment::query()->heldBy($holder->type, $holder->id);
    }

    /**
     * Every direct permission grant of this model, ended ones included.
     *
     * @return Builder<Grant>
     */
    public function vaultGrants(): Builder
    {
        $holder = Holder::of($this);

        return Grant::query()->heldBy($holder->type, $holder->id);
    }
}
