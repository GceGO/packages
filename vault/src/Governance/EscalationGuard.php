<?php

declare(strict_types=1);

namespace Gcego\Vault\Governance;

use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The rules no administrator can get around when giving or taking access.
 * Every governed change (screens, governed API, access request approvals)
 * passes through here; trusted code (seeders, console) does not.
 *
 * 1. Authority: vault.roles.assign (roles) or vault.permissions.grant
 *    (direct permissions) where the change applies; in a tenant, held in
 *    that tenant by a verified member.
 * 2. No self-promotion: nobody gives themselves a role or a permission.
 * 3. No escalation: the actor must hold every permission they give (a role's
 *    own and inherited permissions included). The same applies to revoking,
 *    so nobody can strip access above their own (giving up one's own access
 *    is always allowed).
 * 4. Tenant isolation: tenant roles only in their tenant, and only to
 *    verified members of that tenant.
 * 5. Super-administrator roles: only a super administrator gives or takes them.
 * 6. Sensitive access: when configured, only through an approved request.
 *
 * Every refusal is audited (escalation.blocked) with the rule that applied.
 */
final readonly class EscalationGuard
{
    public function __construct(
        private Authority $authority,
        private PermissionEvaluator $evaluator,
        private Context $context,
        private VaultConfig $config,
        private AuditLog $audit,
    ) {}

    /**
     * @throws VaultAuthorizationException
     */
    public function assignRole(Model $actor, Model $subject, Role $role, string $tenant, bool $approved = false): void
    {
        $this->check($actor, $subject, 'role:'.$role->name, $tenant, function () use ($actor, $subject, $role, $tenant, $approved): void {
            $this->authority->authorize($actor, VaultPermissions::RolesAssign, $tenant);
            $this->notSelf($actor, $subject);
            $this->member($subject, $tenant);

            if (! $role->availableIn($tenant)) {
                throw VaultAuthorizationException::because('foreign_tenant');
            }

            if ($role->is_super && ! $this->authority->isSuper($actor)) {
                throw VaultAuthorizationException::because('super_required');
            }

            $this->covers($actor, $this->rolePermissions($role, $tenant), $tenant);

            if (! $approved && $this->config->sensitiveRequiresApproval && $this->isSensitiveRole($role)) {
                throw VaultAuthorizationException::because('approval_required');
            }
        });
    }

    /**
     * @throws VaultAuthorizationException
     */
    public function revokeRole(Model $actor, Model $subject, Role $role, string $tenant): void
    {
        $this->check($actor, $subject, 'role:'.$role->name, $tenant, function () use ($actor, $subject, $role, $tenant): void {
            $this->authority->authorize($actor, VaultPermissions::RolesAssign, $tenant);

            if (Holder::of($actor)->is(Holder::of($subject))) {
                return;
            }

            if ($role->is_super && ! $this->authority->isSuper($actor)) {
                throw VaultAuthorizationException::because('super_required');
            }

            $this->covers($actor, $this->rolePermissions($role, $tenant), $tenant);
        });
    }

    /**
     * @throws VaultAuthorizationException
     */
    public function grantPermission(Model $actor, Model $subject, Permission $permission, string $tenant, bool $approved = false): void
    {
        $this->check($actor, $subject, 'permission:'.$permission->name, $tenant, function () use ($actor, $subject, $permission, $tenant, $approved): void {
            $this->authority->authorize($actor, VaultPermissions::PermissionsGrant, $tenant);
            $this->notSelf($actor, $subject);
            $this->member($subject, $tenant);
            $this->covers($actor, [$permission->name], $tenant);

            if (! $approved && $this->config->sensitiveRequiresApproval && $permission->is_sensitive) {
                throw VaultAuthorizationException::because('approval_required');
            }
        });
    }

    /**
     * @throws VaultAuthorizationException
     */
    public function revokePermission(Model $actor, Model $subject, Permission $permission, string $tenant): void
    {
        $this->check($actor, $subject, 'permission:'.$permission->name, $tenant, function () use ($actor, $subject, $permission, $tenant): void {
            $this->authority->authorize($actor, VaultPermissions::PermissionsGrant, $tenant);

            if (! Holder::of($actor)->is(Holder::of($subject))) {
                $this->covers($actor, [$permission->name], $tenant);
            }
        });
    }

    /**
     * Changing what a role grants: the actor must hold every permission added or removed,
     * and every permission of roles added to or removed from its inheritance.
     *
     * @param  list<string>  $permissions
     *
     * @throws VaultAuthorizationException
     */
    public function changeRole(Model $actor, Role $role, array $permissions): void
    {
        $this->check($actor, null, 'role:'.$role->name, $role->tenant, function () use ($actor, $role, $permissions): void {
            $this->authority->authorize($actor, VaultPermissions::RolesUpdate, $role->tenant);

            if ($role->is_super && ! $this->authority->isSuper($actor)) {
                throw VaultAuthorizationException::because('super_required');
            }

            $this->covers($actor, $permissions, $role->tenant);
        });
    }

    /**
     * Creating or importing roles: the actor must hold every permission the new roles grant.
     *
     * @param  list<string>  $permissions
     *
     * @throws VaultAuthorizationException
     */
    public function assertCovers(Model $actor, array $permissions, string $tenant, string $target): void
    {
        $this->check($actor, null, $target, $tenant, function () use ($actor, $permissions, $tenant): void {
            $this->covers($actor, array_values(array_unique($permissions)), $tenant);
        });
    }

    /**
     * Every permission a role grants where it would be assigned.
     *
     * @return list<string>
     */
    public function rolePermissions(Role $role, string $tenant): array
    {
        $graph = $this->evaluator->graph();
        $names = [];

        foreach (array_keys($graph->permissionsOf($role->id)) as $id) {
            $permission = $graph->permissions[$id] ?? null;

            if ($permission !== null && $this->evaluator->appliesIn($permission['scope'], $tenant)) {
                $names[] = $permission['name'];
            }
        }

        return $names;
    }

    public function isSensitiveRole(Role $role): bool
    {
        if ($role->is_super || $role->is_protected || $this->config->isProtected($role->name)) {
            return true;
        }

        $graph = $this->evaluator->graph();

        foreach (array_keys($graph->permissionsOf($role->id)) as $id) {
            if ($graph->permissions[$id]['sensitive'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The permissions the actor does not hold where the change applies.
     *
     * @param  iterable<string>  $permissions
     * @return list<string>
     */
    public function missing(Model $actor, iterable $permissions, string $tenant): array
    {
        $missing = [];

        foreach ($permissions as $permission) {
            if ($this->evaluator->allows($actor, $permission, $tenant, $this->context->panel()) !== true) {
                $missing[] = $permission;
            }
        }

        return $missing;
    }

    /**
     * @param  iterable<string>  $permissions
     */
    private function covers(Model $actor, iterable $permissions, string $tenant): void
    {
        $missing = $this->missing($actor, $permissions, $tenant);

        if ($missing !== []) {
            throw VaultAuthorizationException::because('beyond_authority', ['count' => count($missing)]);
        }
    }

    private function notSelf(Model $actor, Model $subject): void
    {
        if (Holder::of($actor)->is(Holder::of($subject))) {
            throw VaultAuthorizationException::because('self_assignment');
        }
    }

    private function member(Model $subject, string $tenant): void
    {
        if ($tenant !== Scope::Global && (! $subject instanceof Authenticatable || ! $this->context->isMember($subject, $tenant))) {
            throw VaultAuthorizationException::because('not_member');
        }
    }

    /**
     * @param  callable(): void  $rules
     */
    private function check(Model $actor, ?Model $subject, string $target, string $tenant, callable $rules): void
    {
        try {
            $rules();
        } catch (VaultAuthorizationException $exception) {
            $this->audit->record(AuditEvent::EscalationBlocked, Holder::of($actor), $subject === null ? null : Holder::of($subject), $target, $tenant, payload: ['rule' => $exception->rule()]);

            throw $exception;
        }
    }
}
