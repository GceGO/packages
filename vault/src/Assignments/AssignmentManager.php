<?php

declare(strict_types=1);

namespace Gcego\Vault\Assignments;

use DateTimeInterface;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Enums\GrantSource;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Governance\EscalationGuard;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\PermissionRegistry;
use Gcego\Vault\Roles\RoleManager;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\VaultConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Gives and takes roles and direct permissions.
 *
 * Governed methods (assignRole, revokeRole, grantPermission,
 * revokePermission, end) take the acting user first and apply every
 * EscalationGuard rule. Use them for anything a person initiates.
 *
 * Trusted methods (*Trusted) are for application code that is itself the
 * authority: seeders, console commands, approved requests, bridges. They
 * skip the actor rules but keep every structural rule.
 *
 * Structural rules (always): known, unarchived role available in the scope;
 * a super-administrator role only globally; a valid scope; expiry in the
 * future and within governance.max_temporary_days; the last active holder of
 * a protected role cannot be removed. Assigning is idempotent: assigning a
 * held role again only changes its expiry. Each change invalidates the
 * holder's cached permissions at once and is audited with its provenance.
 */
final readonly class AssignmentManager
{
    public function __construct(
        private RoleManager $roles,
        private PermissionRegistry $permissions,
        private EscalationGuard $guard,
        private AuditLog $audit,
        private Invalidation $invalidation,
        private VaultConfig $config,
    ) {}

    public function assignRole(Model $actor, Model $subject, Role|string $role, string $tenant = Scope::Global, ?DateTimeInterface $until = null, ?string $reason = null): Assignment
    {
        $role = $this->role($role, $tenant);
        $this->guard->assignRole($actor, $subject, $role, $tenant);

        return $this->assignTrusted($subject, $role, $tenant, $until, $reason, GrantSource::Manual, Holder::of($actor));
    }

    public function revokeRole(Model $actor, Model $subject, Role|string $role, string $tenant = Scope::Global, ?string $reason = null): bool
    {
        $role = $this->role($role, $tenant, allowArchived: true);
        $this->guard->revokeRole($actor, $subject, $role, $tenant);

        return $this->revokeRoleTrusted($subject, $role, $tenant, $reason, Holder::of($actor));
    }

    public function grantPermission(Model $actor, Model $subject, Permission|string $permission, string $tenant = Scope::Global, ?DateTimeInterface $until = null, ?string $reason = null): Grant
    {
        $permission = $permission instanceof Permission ? $permission : $this->permissions->require($permission);
        $this->guard->grantPermission($actor, $subject, $permission, $tenant);

        return $this->grantTrusted($subject, $permission, $tenant, $until, $reason, GrantSource::Manual, Holder::of($actor));
    }

    public function revokePermission(Model $actor, Model $subject, Permission|string $permission, string $tenant = Scope::Global, ?string $reason = null): bool
    {
        $permission = $permission instanceof Permission ? $permission : $this->permissions->require($permission);
        $this->guard->revokePermission($actor, $subject, $permission, $tenant);

        return $this->revokePermissionTrusted($subject, $permission, $tenant, $reason, Holder::of($actor));
    }

    /**
     * End one assignment or grant by its record (from the screens), governed.
     */
    public function end(Model $actor, Assignment|Grant $record, ?string $reason = null): bool
    {
        $holder = $record->holder;

        if (! $holder instanceof Model) {
            throw VaultValidationException::make('unknown_holder');
        }

        return $record instanceof Assignment
            ? $this->revokeRole($actor, $holder, $record->role, $record->tenant, $reason)
            : $this->revokePermission($actor, $holder, $record->permission, $record->tenant, $reason);
    }

    public function assignTrusted(Model $subject, Role|string $role, string $tenant = Scope::Global, ?DateTimeInterface $until = null, ?string $reason = null, GrantSource $source = GrantSource::System, ?Holder $by = null, ?int $request = null): Assignment
    {
        $role = $this->role($role, $tenant);

        if ($role->is_super && $tenant !== Scope::Global) {
            throw VaultValidationException::make('super_global_only', field: 'tenant');
        }

        /** @var Assignment */
        return $this->give(new Assignment, 'role_id', $role->id, 'role:'.$role->name, AuditEvent::RoleAssigned, $subject, $tenant, $until, $reason, $source, $by, $request);
    }

    public function revokeRoleTrusted(Model $subject, Role|string $role, string $tenant = Scope::Global, ?string $reason = null, ?Holder $by = null): bool
    {
        $role = $this->role($role, $tenant, allowArchived: true);

        return $this->take(Assignment::class, 'role_id', $role->id, 'role:'.$role->name, AuditEvent::RoleRevoked, $subject, $tenant, $reason, $by, $role);
    }

    public function grantTrusted(Model $subject, Permission|string $permission, string $tenant = Scope::Global, ?DateTimeInterface $until = null, ?string $reason = null, GrantSource $source = GrantSource::System, ?Holder $by = null, ?int $request = null): Grant
    {
        $permission = $permission instanceof Permission ? $permission : $this->permissions->require($permission);

        if (! $permission->appliesIn($tenant)) {
            throw VaultValidationException::make('permission_wrong_scope', ['permission' => $permission->name], 'tenant');
        }

        /** @var Grant */
        return $this->give(new Grant, 'permission_id', $permission->id, 'permission:'.$permission->name, AuditEvent::PermissionGranted, $subject, $tenant, $until, $reason, $source, $by, $request);
    }

    public function revokePermissionTrusted(Model $subject, Permission|string $permission, string $tenant = Scope::Global, ?string $reason = null, ?Holder $by = null): bool
    {
        $permission = $permission instanceof Permission ? $permission : $this->permissions->require($permission);

        return $this->take(Grant::class, 'permission_id', $permission->id, 'permission:'.$permission->name, AuditEvent::PermissionRevoked, $subject, $tenant, $reason, $by);
    }

    /**
     * Close every assignment and grant whose time ran out (they already stopped
     * counting when they expired), audit each one, and return how many.
     */
    public function expire(): int
    {
        $closed = 0;

        foreach ([Assignment::class, Grant::class] as $class) {
            $class::query()->whereNotNull('active_key')->whereNotNull('expires_at')->where('expires_at', '<=', Date::now())
                ->with($class === Assignment::class ? 'role' : 'permission')
                ->chunkById(200, function ($records) use (&$closed): void {
                    foreach ($records as $record) {
                        $updated = $record->newQuery()->whereKey($record->getKey())->whereNotNull('active_key')
                            ->update(['active_key' => null, 'revoked_at' => $record->expires_at, 'ended_as' => 'expired', 'updated_at' => Date::now()]);

                        if ($updated === 0) {
                            continue;
                        }

                        $closed++;
                        $holder = new Holder($record->model_type, $record->model_id);
                        $this->invalidation->holder($holder);
                        $this->audit->record(AuditEvent::AccessExpired, null, $holder, $record instanceof Assignment ? 'role:'.$record->role?->name : 'permission:'.$record->permission?->name, $record->tenant, payload: ['expired_at' => $record->expires_at?->toIso8601String()]);
                    }
                });
        }

        return $closed;
    }

    private function role(Role|string $role, string $tenant, bool $allowArchived = false): Role
    {
        if (! Scope::isValid($tenant)) {
            throw VaultValidationException::make('invalid_scope', field: 'tenant');
        }

        $role = $role instanceof Role ? $role : $this->roles->require($role, $tenant);

        if (! $role->availableIn($tenant)) {
            throw VaultAuthorizationException::because('foreign_tenant');
        }

        if (! $allowArchived && $role->isArchived()) {
            throw VaultValidationException::make('role_archived', ['role' => $role->name], 'role');
        }

        return $role;
    }

    private function give(Assignment|Grant $record, string $column, int $target, string $label, AuditEvent $event, Model $subject, string $tenant, ?DateTimeInterface $until, ?string $reason, GrantSource $source, ?Holder $by, ?int $request): Assignment|Grant
    {
        $holder = Holder::of($subject);
        $until = $this->until($until);
        $key = sha1($column.'|'.$target.'|'.$holder->type.'|'.$holder->id.'|'.$tenant);
        $now = Date::now();
        $connection = $record->getConnection();
        $changed = false;

        $result = $connection->transaction(function () use ($connection, $record, $column, $target, $holder, $tenant, $until, $reason, $source, $by, $request, $key, $now, &$changed): Assignment|Grant {
            // An expired row keeps its key until closed: close it so the access can be given again.
            $record->newQuery()->where('active_key', $key)->whereNotNull('expires_at')->where('expires_at', '<=', $now)
                ->update(['active_key' => null, 'revoked_at' => $now, 'ended_as' => 'expired', 'updated_at' => $now]);

            $existing = $record->newQuery()->where('active_key', $key)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->expires_at?->getTimestamp() !== $until?->getTimestamp()) {
                    $existing->forceFill(['expires_at' => $until])->save();
                    $changed = true;
                }

                return $existing;
            }

            $record->forceFill([
                $column => $target,
                'model_type' => $holder->type,
                'model_id' => $holder->id,
                'tenant' => $tenant,
                'expires_at' => $until,
                'source' => $source->value,
                'request_id' => $request,
                'reason' => $reason === null ? null : mb_substr(trim($reason), 0, 500),
                'granted_by_type' => $by?->type,
                'granted_by_id' => $by?->id,
                'active_key' => $key,
            ]);

            try {
                // A savepoint, so a lost race leaves the outer transaction usable (PostgreSQL).
                $connection->transaction(static fn (): bool => $record->save());
            } catch (QueryException $exception) {
                // A concurrent request gave the same access first: one active row, never two.
                $winner = $record->newQuery()->where('active_key', $key)->first();

                return $winner ?? throw $exception;
            }

            $changed = true;

            return $record;
        });

        if ($changed) {
            $this->invalidation->holder($holder);
            $this->audit->record($event, $by, $holder, $label, $tenant, $reason, [
                'source' => $source->value,
                'expires_at' => $until?->toIso8601String(),
                'request' => $request,
            ]);
        }

        return $result;
    }

    /**
     * @param  class-string<Assignment|Grant>  $class
     */
    private function take(string $class, string $column, int $target, string $label, AuditEvent $event, Model $subject, string $tenant, ?string $reason, ?Holder $by, ?Role $role = null): bool
    {
        $holder = Holder::of($subject);
        $now = Date::now();

        $taken = (new $class)->getConnection()->transaction(function () use ($class, $column, $target, $holder, $tenant, $reason, $by, $role, $now): bool {
            $record = $class::query()->heldBy($holder->type, $holder->id)->where($column, $target)->where('tenant', $tenant)->whereNotNull('active_key')->lockForUpdate()->first();

            if ($record === null || ! $record->isActive()) {
                return false;
            }

            if ($role !== null && ($role->is_protected || $this->config->isProtected($role->name))) {
                $others = Assignment::query()->where('role_id', $role->id)->where('tenant', $tenant)->active()->whereKeyNot($record->getKey())->lockForUpdate()->count();

                if ($others === 0) {
                    throw VaultAuthorizationException::because('last_holder', ['role' => $role->label]);
                }
            }

            $record->forceFill([
                'revoked_at' => $now,
                'ended_as' => 'revoked',
                'revoked_by_type' => $by?->type,
                'revoked_by_id' => $by?->id,
                'revoke_reason' => $reason === null ? null : mb_substr(trim($reason), 0, 500),
                'active_key' => null,
            ])->save();

            return true;
        });

        if ($taken) {
            $this->invalidation->holder($holder);
            $this->audit->record($event, $by, $holder, $label, $tenant, $reason);
        }

        return $taken;
    }

    private function until(?DateTimeInterface $until): ?Carbon
    {
        if ($until === null) {
            return null;
        }

        $until = Date::instance($until);

        if (! $until->isFuture()) {
            throw VaultValidationException::make('expiry_in_past', field: 'until');
        }

        if ($this->config->maxTemporaryDays !== null && $until->greaterThan(Date::now()->addDays($this->config->maxTemporaryDays))) {
            throw VaultValidationException::make('expiry_too_far', ['days' => $this->config->maxTemporaryDays], 'until');
        }

        return $until;
    }
}
