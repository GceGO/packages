<?php

declare(strict_types=1);

namespace Gcego\Vault;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Vault\Assignments\AssignmentManager;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\EffectivePermissions;
use Gcego\Vault\Authorization\Explanation;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Governance\AccessRequests;
use Gcego\Vault\Permissions\PermissionRegistry;
use Gcego\Vault\Roles\RoleManager;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Tenancy\Scope;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point of gcego/vault (also the Vault facade):
 *
 *     Vault::allows($user, 'reports.export');                 // global
 *     Vault::allows($user, 'grades.update', $tenant);         // in a tenant (Tenant or "institution:7")
 *     Vault::explain($user, 'subjects.delete');               // why (diagnostics)
 *     Vault::roles()->create($admin, [...]);                   // governed role management
 *     Vault::assignments()->assignRole($admin, $user, 'teacher', until: now()->addWeek());
 *     Vault::requests()->submit($user, ['role' => 'editor', 'reason' => '...']);
 *
 * Ordinary authorization stays Laravel's: Gate::authorize('update', $subject),
 * $user->can('reports.export'), gcego/security requirements. Services are
 * resolved per call, so scoped state never outlives its request.
 */
final readonly class Vault
{
    public function __construct(private Container $container) {}

    public function allows(Model $holder, string $permission, Tenant|string|null $tenant = null): bool
    {
        return $this->evaluator()->allows($holder, $permission, self::scope($tenant), $this->container->make(Context::class)->panel()) === true;
    }

    public function explain(Model $holder, string $permission, Tenant|string|null $tenant = null, ?string $panel = null): Explanation
    {
        return $this->evaluator()->explain($holder, $permission, self::scope($tenant), $panel ?? $this->container->make(Context::class)->panel());
    }

    public function effective(Model $holder, Tenant|string|null $tenant = null, ?string $panel = null): EffectivePermissions
    {
        return $this->evaluator()->effective($holder, self::scope($tenant), $panel ?? $this->container->make(Context::class)->panel());
    }

    /**
     * Whether the signed-in user holds the permission (in the current tenant with $tenant true).
     */
    public function can(string $permission, bool $tenant = false): bool
    {
        $context = $this->container->make(Context::class);
        $actor = $context->actor();

        return $actor !== null && $this->allows($actor, $permission, $tenant ? $context->tenant() : null);
    }

    public function roles(): RoleManager
    {
        return $this->container->make(RoleManager::class);
    }

    public function permissions(): PermissionRegistry
    {
        return $this->container->make(PermissionRegistry::class);
    }

    public function assignments(): AssignmentManager
    {
        return $this->container->make(AssignmentManager::class);
    }

    public function requests(): AccessRequests
    {
        return $this->container->make(AccessRequests::class);
    }

    public function evaluator(): PermissionEvaluator
    {
        return $this->container->make(PermissionEvaluator::class);
    }

    /**
     * Register declared permissions, then create and update the configured system roles (trusted).
     *
     * @return array{permissions: array{created: list<string>, updated: list<string>, stale: list<string>, pruned: list<string>}, roles: array{created: list<string>, updated: list<string>, orphaned: list<string>}}
     */
    public function sync(?Model $by = null, bool $prune = false): array
    {
        $holder = $by === null ? null : Holder::of($by);

        return ['permissions' => $this->permissions()->sync($holder, $prune), 'roles' => $this->roles()->syncSystem($holder)];
    }

    /**
     * Invalidate every cached permission set (they are rebuilt on next use).
     */
    public function flush(?Model $by = null): void
    {
        $this->container->make(Invalidation::class)->definitions();
        $this->container->make(AuditLog::class)->record(AuditEvent::CacheCleared, $by === null ? null : Holder::of($by));
    }

    public static function scope(Tenant|string|null $tenant): string
    {
        if ($tenant instanceof Tenant) {
            return $tenant->id();
        }

        $tenant ??= Scope::Global;

        return Scope::isValid($tenant) ? $tenant : throw VaultValidationException::make('invalid_scope', field: 'tenant');
    }
}
