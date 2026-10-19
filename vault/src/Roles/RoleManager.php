<?php

declare(strict_types=1);

namespace Gcego\Vault\Roles;

use Gcego\Panels\PanelManager;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Enums\SyncMode;
use Gcego\Vault\Exceptions\CircularInheritanceException;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Governance\EscalationGuard;
use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Support\Names;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Creates, changes, archives and deletes roles, their permissions and their
 * inheritance. Governed methods take the acting user first: they need the
 * matching vault.roles.* permission where the role lives, and the actor must
 * hold every permission they add to or remove from a role (EscalationGuard).
 *
 * - Names are lowercase slugs, unique per tenant; a tenant role never
 *   shadows a global one. System roles (from configuration) cannot be
 *   renamed or deleted, and roles synchronized with "replace" cannot be
 *   edited (the next sync would silently undo the edit).
 * - Every permission's dependencies must be granted by the role itself or
 *   by a role it inherits.
 * - Inheritance never forms a cycle; super-administrator and archived roles
 *   cannot be inherited; a tenant role inherits only global roles or roles
 *   of its own tenant.
 * - Protected roles cannot be archived or deleted.
 */
final readonly class RoleManager
{
    public function __construct(
        private Authority $authority,
        private EscalationGuard $guard,
        private PermissionEvaluator $evaluator,
        private AuditLog $audit,
        private Invalidation $invalidation,
        private VaultConfig $config,
        private Container $container,
    ) {}

    /**
     * The role with this name usable in the scope: the tenant's own first, then the global one.
     */
    public function find(string $name, string $tenant = Scope::Global): ?Role
    {
        return Role::query()->where('name', $name)->availableIn($tenant)->orderByRaw('CASE WHEN tenant = ? THEN 0 ELSE 1 END', [$tenant])->first();
    }

    public function require(string $name, string $tenant = Scope::Global): Role
    {
        return $this->find($name, $tenant) ?? throw VaultValidationException::make('unknown_role', ['role' => $name], 'role');
    }

    /**
     * @param  array{name?: mixed, label?: mixed, description?: mixed, group?: mixed, panel?: mixed, permissions?: mixed, inherits?: mixed}  $data
     */
    public function create(Model $actor, array $data, string $tenant = Scope::Global): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesCreate, $tenant);
        $permissions = $this->names($data['permissions'] ?? []);
        $inherits = $this->names($data['inherits'] ?? []);
        $this->guard->assertCovers($actor, [...$permissions, ...$this->inheritedPermissions($inherits, $tenant)], $tenant, 'role:'.(is_string($data['name'] ?? null) ? $data['name'] : '?'));

        $role = $this->transaction(function () use ($data, $tenant, $permissions, $inherits): Role {
            $role = $this->insert($data, $tenant);
            $this->applyInheritance($role, $inherits);
            $this->applyPermissions($role, $permissions);

            return $role;
        });

        $this->audit->record(AuditEvent::RoleCreated, Holder::of($actor), target: 'role:'.$role->name, tenant: $tenant, payload: ['permissions' => $permissions, 'inherits' => $inherits]);

        return $role;
    }

    /**
     * @param  array{name?: mixed, label?: mixed, description?: mixed, group?: mixed, panel?: mixed}  $data
     */
    public function update(Model $actor, Role $role, array $data): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesUpdate, $role->tenant);
        $name = $data['name'] ?? $role->name;

        if ($name !== $role->name) {
            if ($role->is_system) {
                throw VaultAuthorizationException::because('system_role');
            }

            $this->assertName($name, $role->tenant, $role->id);
        }

        $role->forceFill(['name' => $name, ...$this->details($data, $role)]);
        $changed = array_keys($role->getDirty());

        if ($changed !== []) {
            $role->save();
            $this->invalidation->definitions();
            $this->audit->record(AuditEvent::RoleUpdated, Holder::of($actor), target: 'role:'.$role->name, tenant: $role->tenant, payload: ['changed' => $changed]);
        }

        return $role;
    }

    /**
     * Set exactly these permissions on the role (governed).
     *
     * @param  list<string>  $permissions
     */
    public function syncPermissions(Model $actor, Role $role, array $permissions): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesUpdate, $role->tenant);
        $this->editable($role);
        $permissions = $this->names($permissions);
        $current = $role->permissions()->pluck('name')->all();
        $added = array_values(array_diff($permissions, $current));
        $removed = array_values(array_diff($current, $permissions));

        if ($added === [] && $removed === []) {
            return $role;
        }

        $this->guard->changeRole($actor, $role, [...$added, ...$removed]);
        $this->transaction(fn () => $this->applyPermissions($role, $permissions));
        $this->audit->record(AuditEvent::RolePermissionsChanged, Holder::of($actor), target: 'role:'.$role->name, tenant: $role->tenant, payload: ['added' => $added, 'removed' => $removed]);

        return $role;
    }

    /**
     * Set exactly these inherited roles (governed).
     *
     * @param  list<string>  $inherits
     */
    public function syncInheritance(Model $actor, Role $role, array $inherits): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesUpdate, $role->tenant);
        $this->editable($role);
        $inherits = $this->names($inherits);
        $current = $role->parents()->pluck('name')->all();
        $added = array_values(array_diff($inherits, $current));
        $removed = array_values(array_diff($current, $inherits));

        if ($added === [] && $removed === []) {
            return $role;
        }

        $this->guard->changeRole($actor, $role, $this->inheritedPermissions([...$added, ...$removed], $role->tenant));
        $this->transaction(fn () => $this->applyInheritance($role, $inherits));
        $this->audit->record(AuditEvent::RoleInheritanceChanged, Holder::of($actor), target: 'role:'.$role->name, tenant: $role->tenant, payload: ['added' => $added, 'removed' => $removed]);

        return $role;
    }

    public function archive(Model $actor, Role $role, ?string $reason = null): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesDelete, $role->tenant);

        if ($role->is_protected || $this->config->isProtected($role->name) || $role->is_super) {
            throw VaultAuthorizationException::because('protected_role');
        }

        $this->guard->changeRole($actor, $role, $this->guard->rolePermissions($role, $role->tenant));

        if (! $role->isArchived()) {
            $role->forceFill(['archived_at' => Date::now()])->save();
            $this->invalidation->definitions();
            $this->audit->record(AuditEvent::RoleArchived, Holder::of($actor), target: 'role:'.$role->name, tenant: $role->tenant, reason: $reason);
        }

        return $role;
    }

    public function restore(Model $actor, Role $role): Role
    {
        $this->authority->authorize($actor, VaultPermissions::RolesDelete, $role->tenant);
        $this->guard->changeRole($actor, $role, $this->guard->rolePermissions($role, $role->tenant));

        if ($role->isArchived()) {
            $role->forceFill(['archived_at' => null])->save();
            $this->invalidation->definitions();
            $this->audit->record(AuditEvent::RoleRestored, Holder::of($actor), target: 'role:'.$role->name, tenant: $role->tenant);
        }

        return $role;
    }

    /**
     * Delete a custom role nobody holds; its ended assignments and requests go with it (the audit log keeps them).
     */
    public function delete(Model $actor, Role $role): void
    {
        $this->authority->authorize($actor, VaultPermissions::RolesDelete, $role->tenant);

        if ($role->is_system) {
            throw VaultAuthorizationException::because('system_role');
        }

        if ($role->is_protected || $this->config->isProtected($role->name)) {
            throw VaultAuthorizationException::because('protected_role');
        }

        $holders = Assignment::query()->where('role_id', $role->id)->active()->count();

        if ($holders > 0) {
            throw VaultValidationException::make('role_in_use', ['count' => $holders]);
        }

        if ($role->children()->exists()) {
            throw VaultValidationException::make('role_inherited', ['roles' => implode(', ', $role->children()->pluck('name')->all())]);
        }

        $this->guard->changeRole($actor, $role, $this->guard->rolePermissions($role, $role->tenant));
        [$name, $tenant] = [$role->name, $role->tenant];

        $this->transaction(static function () use ($role): void {
            AccessRequest::query()->where('role_id', $role->id)->delete();
            $role->delete();
        });

        $this->invalidation->definitions();
        $this->audit->record(AuditEvent::RoleDeleted, Holder::of($actor), target: 'role:'.$name, tenant: $tenant);
    }

    public function duplicate(Model $actor, Role $role, string $name, ?string $label = null): Role
    {
        $copy = $this->create($actor, [
            'name' => $name,
            'label' => $label ?? $role->label.' (copy)',
            'description' => $role->description,
            'group' => $role->group,
            'panel' => $role->panel,
            'permissions' => $role->permissions()->pluck('name')->all(),
            'inherits' => $role->parents()->pluck('name')->all(),
        ], $role->tenant);

        $this->audit->record(AuditEvent::RoleDuplicated, Holder::of($actor), target: 'role:'.$copy->name, tenant: $role->tenant, payload: ['from' => $role->name]);

        return $copy;
    }

    /**
     * Role definitions as plain data (JSON-ready), the governed counterpart of import().
     *
     * @param  list<string>|null  $names  Null: every role of the scope.
     * @return array{format: string, version: int, exported_at: string, roles: list<array{name: string, label: string, description: string|null, group: string|null, panel: string|null, system: bool, permissions: list<string>, inherits: list<string>}>}
     */
    public function export(Model $actor, ?array $names = null, string $tenant = Scope::Global): array
    {
        $this->authority->authorize($actor, VaultPermissions::RolesExport, $tenant);
        $roles = Role::query()->where('tenant', $tenant)->with(['permissions:id,name', 'parents:id,name'])->orderBy('name');

        if ($names !== null) {
            $roles->whereIn('name', $names);
        }

        return [
            'format' => 'gcego-vault-roles',
            'version' => 1,
            'exported_at' => Date::now()->toIso8601String(),
            'roles' => $roles->get()->map(static fn (Role $role): array => [
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->description,
                'group' => $role->group,
                'panel' => $role->panel,
                'system' => $role->is_system,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                'inherits' => $role->parents->pluck('name')->sort()->values()->all(),
            ])->all(),
        ];
    }

    /**
     * Import role definitions, all or nothing (governed). New roles are created; existing custom
     * roles are updated only with $overwrite; system roles are never changed by an import.
     *
     * @param  array<mixed>  $payload
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function import(Model $actor, array $payload, string $tenant = Scope::Global, bool $overwrite = false): array
    {
        $this->authority->authorize($actor, VaultPermissions::RolesImport, $tenant);

        if (($payload['format'] ?? null) !== 'gcego-vault-roles' || ($payload['version'] ?? null) !== 1 || ! is_array($payload['roles'] ?? null) || count($payload['roles']) > 500) {
            throw VaultValidationException::make('invalid_import', field: 'file');
        }

        $result = ['created' => [], 'updated' => [], 'skipped' => []];
        $plan = [];
        $granted = [];

        foreach ($payload['roles'] as $definition) {
            if (! is_array($definition) || ! is_string($definition['name'] ?? null)) {
                throw VaultValidationException::make('invalid_import', field: 'file');
            }

            $existing = Role::query()->where('tenant', $tenant)->where('name', $definition['name'])->first();

            if ($existing !== null && ($existing->is_system || ! $overwrite)) {
                $result['skipped'][] = $existing->name;

                continue;
            }

            $permissions = $this->names($definition['permissions'] ?? []);
            $inherits = $this->names($definition['inherits'] ?? []);
            $plan[] = [$definition, $existing, $permissions, $inherits];
            $granted = [...$granted, ...$permissions];
            $result[$existing === null ? 'created' : 'updated'][] = $definition['name'];
        }

        // Every permission the import grants must be held by the importer (inherited roles from the file included).
        $this->guard->assertCovers($actor, $granted, $tenant, 'roles:import');

        $this->transaction(function () use ($plan, $tenant): void {
            $roles = [];

            foreach ($plan as $index => [$definition, $existing]) {
                $roles[$index] = $existing ?? $this->insert($definition, $tenant);

                if ($existing !== null) {
                    $existing->forceFill($this->details($definition, $existing))->save();
                }
            }

            $this->invalidation->definitions();

            foreach ($plan as $index => [, , , $inherits]) {
                $this->applyInheritance($roles[$index], $inherits);
            }

            foreach ($plan as $index => [, , $permissions]) {
                $this->applyPermissions($roles[$index], $permissions);
            }
        });

        $this->audit->record(AuditEvent::RolesImported, Holder::of($actor), tenant: $tenant, payload: ['created' => $result['created'], 'updated' => $result['updated'], 'skipped' => $result['skipped']]);

        return $result;
    }

    /**
     * Create or update the configured system roles (trusted: gcego:vault:sync). Never deletes a role.
     *
     * @return array{created: list<string>, updated: list<string>, orphaned: list<string>}
     */
    public function syncSystem(?Holder $by = null): array
    {
        $result = ['created' => [], 'updated' => [], 'orphaned' => []];

        $this->transaction(function () use (&$result): void {
            $catalog = Permission::query()->pluck('name')->all();
            $applied = [];

            foreach ($this->config->roles as $name => $definition) {
                $role = Role::query()->where('tenant', Scope::Global)->where('name', $name)->first();
                $isNew = $role === null;
                $role ??= new Role;
                $role->forceFill([
                    'name' => $name,
                    'tenant' => Scope::Global,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'group' => $definition['group'],
                    'panel' => $definition['panel'],
                    'is_system' => true,
                    'is_protected' => $definition['protected'],
                    'is_super' => $definition['super'],
                ]);
                $dirty = $role->isDirty();
                $role->save();
                $applied[$name] = [$role, $definition, $isNew];

                if ($isNew) {
                    $result['created'][] = $name;
                } elseif ($dirty) {
                    $result['updated'][] = $name;
                }
            }

            $this->invalidation->definitions();

            foreach ($applied as $name => [$role, $definition, $isNew]) {
                if (! $isNew && $definition['sync'] === SyncMode::Create) {
                    continue;
                }

                $parents = $definition['inherits'];
                $permissions = $this->expand($definition['permissions'], $catalog);

                if ($definition['sync'] === SyncMode::Merge && ! $isNew) {
                    $parents = array_values(array_unique([...$role->parents()->pluck('name')->all(), ...$parents]));
                    $permissions = array_values(array_unique([...$role->permissions()->pluck('name')->all(), ...$permissions]));
                }

                $before = [$role->parents()->pluck('name')->sort()->values()->all(), $role->permissions()->pluck('name')->sort()->values()->all()];
                $this->applyInheritance($role, $parents);
                $this->applyPermissions($role, $permissions, autoDependencies: true);
                $after = [$role->parents()->pluck('name')->sort()->values()->all(), $role->permissions()->pluck('name')->sort()->values()->all()];

                if (! $isNew && $before !== $after && ! in_array($name, $result['updated'], true)) {
                    $result['updated'][] = $name;
                }
            }

            $result['orphaned'] = Role::query()->where('is_system', true)->whereNotIn('name', array_keys($this->config->roles))->pluck('name')->all();
        });

        foreach ($result['created'] as $name) {
            $this->audit->record(AuditEvent::RoleCreated, $by, target: 'role:'.$name, payload: ['source' => 'sync']);
        }

        foreach ($result['updated'] as $name) {
            $this->audit->record(AuditEvent::RoleUpdated, $by, target: 'role:'.$name, payload: ['source' => 'sync']);
        }

        return $result;
    }

    /**
     * Whether the role's permissions are owned by configuration (sync mode "replace").
     */
    public function isLocked(Role $role): bool
    {
        return $role->is_system && $role->tenant === Scope::Global && ($this->config->roles[$role->name]['sync'] ?? null) === SyncMode::Replace;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function insert(array $data, string $tenant): Role
    {
        if (! Scope::isValid($tenant)) {
            throw VaultValidationException::make('invalid_scope', field: 'tenant');
        }

        $name = $data['name'] ?? null;
        $this->assertName($name, $tenant);
        $role = new Role;
        $role->forceFill(['name' => $name, 'tenant' => $tenant, 'is_system' => false, 'is_protected' => false, 'is_super' => false, ...$this->details($data, $role)])->save();
        $this->invalidation->definitions();

        return $role;
    }

    /**
     * A global name must be free everywhere (tenant roles would shadow it); a tenant name must be free in that tenant and globally.
     */
    private function assertName(mixed $name, string $tenant, ?int $except = null): void
    {
        if (! is_string($name) || ! Names::isRole($name)) {
            throw VaultValidationException::make('invalid_role_name', field: 'name');
        }

        $query = Role::query()->where('name', $name)->when($except !== null, static fn ($query) => $query->whereKeyNot($except));

        if ($tenant !== Scope::Global) {
            $query->whereIn('tenant', [$tenant, Scope::Global]);
        }

        if ($query->exists()) {
            throw VaultValidationException::make('role_exists', ['role' => $name], 'name');
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array{label: string, description: string|null, group: string|null, panel: string|null}
     */
    private function details(array $data, Role $role): array
    {
        $label = $data['label'] ?? $role->label ?? (is_string($data['name'] ?? null) ? Names::label($data['name']) : null);
        $description = array_key_exists('description', $data) ? $data['description'] : $role->description;
        $group = array_key_exists('group', $data) ? $data['group'] : $role->group;
        $panel = array_key_exists('panel', $data) ? $data['panel'] : $role->panel;
        $panel = $panel === '' ? null : $panel;

        if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 150) {
            throw VaultValidationException::make('invalid_label', field: 'label');
        }

        if (($description !== null && (! is_string($description) || mb_strlen($description) > 2000)) || ($group !== null && (! is_string($group) || mb_strlen($group) > 100))) {
            throw VaultValidationException::make('invalid_details', field: 'description');
        }

        if ($panel !== null && (! is_string($panel) || ! $this->panelExists($panel))) {
            throw VaultValidationException::make('unknown_panel', field: 'panel');
        }

        return [
            'label' => trim($label),
            'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
            'group' => is_string($group) && trim($group) !== '' ? trim($group) : null,
            'panel' => $panel,
        ];
    }

    private function panelExists(string $panel): bool
    {
        try {
            return $this->container->bound(PanelManager::class) && $this->container->make(PanelManager::class)->has($panel);
        } catch (Throwable) {
            return false;
        }
    }

    private function editable(Role $role): void
    {
        if ($this->isLocked($role)) {
            throw VaultAuthorizationException::because('managed_by_configuration');
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function applyPermissions(Role $role, array $names, bool $autoDependencies = false): void
    {
        $permissions = Permission::query()->whereIn('name', $names)->get(['id', 'name', 'scope', 'requires'])->keyBy('name');
        $unknown = array_values(array_diff($names, $permissions->keys()->all()));

        if ($unknown !== []) {
            throw VaultValidationException::make('unknown_permissions', ['permissions' => implode(', ', array_slice($unknown, 0, 10))], 'permissions');
        }

        if ($role->tenant !== Scope::Global) {
            $global = $permissions->filter(static fn (Permission $permission): bool => $permission->scope === Permission::ScopeGlobal)->keys()->all();

            if ($global !== []) {
                throw VaultValidationException::make('permission_wrong_scope', ['permission' => $global[0]], 'permissions');
            }
        }

        $graph = $this->evaluator->graph();
        $inherited = [];

        foreach ($role->parents()->pluck('gcego_vault_roles.id')->all() as $parent) {
            foreach (array_keys($graph->permissionsOf((int) $parent)) as $id) {
                $inherited[$graph->permissions[$id]['name']] = true;
            }
        }

        $granted = array_fill_keys($names, true);
        $missing = [];

        foreach ($permissions as $permission) {
            foreach ($permission->dependencies() as $required) {
                if (! isset($granted[$required]) && ! isset($inherited[$required])) {
                    $missing[$required] = true;
                }
            }
        }

        if ($missing !== [] && $autoDependencies) {
            $dependencies = Permission::query()->whereIn('name', array_keys($missing))->get(['id', 'name', 'scope', 'requires']);
            $permissions = $permissions->merge($dependencies->keyBy('name'));
            $missing = array_diff_key($missing, $dependencies->keyBy('name')->all());
        }

        if ($missing !== []) {
            throw VaultValidationException::make('missing_dependencies', ['permissions' => implode(', ', array_slice(array_keys($missing), 0, 10))], 'permissions');
        }

        $connection = $role->getConnection();
        $connection->table('gcego_vault_role_permissions')->where('role_id', $role->id)->delete();
        $now = Date::now();

        foreach (array_chunk($permissions->pluck('id')->unique()->values()->all(), 500) as $chunk) {
            $connection->table('gcego_vault_role_permissions')->insert(array_map(static fn (int $id): array => ['role_id' => $role->id, 'permission_id' => $id, 'created_at' => $now], $chunk));
        }

        $this->invalidation->definitions();
    }

    /**
     * @param  list<string>  $names
     */
    private function applyInheritance(Role $role, array $names): void
    {
        $parents = [];

        foreach ($names as $name) {
            $parent = $this->find($name, $role->tenant);

            if ($parent === null) {
                throw VaultValidationException::make('unknown_role', ['role' => $name], 'inherits');
            }

            if ($parent->id === $role->id) {
                throw CircularInheritanceException::make('circular_inheritance', ['role' => $name], 'inherits');
            }

            if ($parent->is_super || $parent->isArchived() || ! $parent->availableIn($role->tenant)) {
                throw VaultValidationException::make('invalid_parent', ['role' => $name], 'inherits');
            }

            $parents[$parent->id] = $parent;
        }

        $graph = $this->evaluator->graph();

        foreach (array_keys($parents) as $parent) {
            if ($graph->wouldCycle($role->id, $parent)) {
                throw CircularInheritanceException::make('circular_inheritance', ['role' => $parents[$parent]->name], 'inherits');
            }
        }

        $connection = $role->getConnection();
        $connection->table('gcego_vault_role_inheritance')->where('role_id', $role->id)->delete();
        $now = Date::now();

        if ($parents !== []) {
            $connection->table('gcego_vault_role_inheritance')->insert(array_map(static fn (int $id): array => ['role_id' => $role->id, 'inherits_id' => $id, 'created_at' => $now], array_keys($parents)));
        }

        $this->invalidation->definitions();
    }

    /**
     * Every permission the named roles grant (with their own inheritance) where they apply.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function inheritedPermissions(array $roles, string $tenant): array
    {
        $permissions = [];

        foreach ($roles as $name) {
            $role = $this->find($name, $tenant);

            if ($role !== null) {
                $permissions = [...$permissions, ...$this->guard->rolePermissions($role, $tenant)];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Configured patterns ("*", "subjects.*", "*.viewAny") expanded against the registry.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $catalog
     * @return list<string>
     */
    private function expand(array $patterns, array $catalog): array
    {
        $names = [];

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*')) {
                $names[] = $pattern;

                continue;
            }

            foreach ($catalog as $name) {
                if (Names::matches($pattern, $name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function names(mixed $names): array
    {
        if (! is_array($names) || count($names) > 5000) {
            throw VaultValidationException::make('invalid_permissions', field: 'permissions');
        }

        return array_values(array_unique(array_filter($names, static fn (mixed $name): bool => is_string($name) && $name !== '')));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        return (new Role)->getConnection()->transaction($callback);
    }
}
