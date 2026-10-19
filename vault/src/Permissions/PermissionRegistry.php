<?php

declare(strict_types=1);

namespace Gcego\Vault\Permissions;

use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Support\Names;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * The permission registry: synchronization from code and discovery, and
 * custom permissions managed by administrators.
 *
 * - Code is the source of truth for declared permissions: sync() creates
 *   and updates them and marks the ones no longer declared as stale; it
 *   never deletes one that is still granted, and never grants anything.
 * - Custom permissions (created in the registry) may be edited and deleted
 *   by holders of vault.permissions.manage; a permission still granted to a
 *   role or a person cannot be deleted.
 * - Names are unique case-insensitively.
 */
final readonly class PermissionRegistry
{
    public function __construct(
        private PermissionDiscovery $discovery,
        private AuditLog $audit,
        private Invalidation $invalidation,
        private PermissionEvaluator $evaluator,
        private Authority $authority,
    ) {}

    public function find(string $name): ?Permission
    {
        return Permission::query()->where('normalized', Names::normalize($name))->first();
    }

    /**
     * @throws VaultValidationException
     */
    public function require(string $name): Permission
    {
        return $this->find($name) ?? throw VaultValidationException::make('unknown_permission', ['permission' => $name], 'permission');
    }

    /**
     * @return array{created: list<string>, updated: list<string>, stale: list<string>, pruned: list<string>}
     */
    public function sync(?Holder $by = null, bool $prune = false): array
    {
        $declared = $this->discovery->discover();
        $result = ['created' => [], 'updated' => [], 'stale' => [], 'pruned' => []];
        $now = Date::now();

        (new Permission)->getConnection()->transaction(function () use ($declared, $prune, $now, &$result): void {
            $existing = Permission::query()->get()->keyBy('normalized');

            foreach ($declared as $name => $definition) {
                $permission = $existing->get(Names::normalize($name)) ?? new Permission;
                $isNew = ! $permission->exists;
                $permission->forceFill([
                    'name' => $name,
                    'normalized' => Names::normalize($name),
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? $permission->description,
                    'group' => $definition['group'],
                    'resource' => $definition['resource'],
                    'ability' => $definition['ability'],
                    'scope' => $definition['scope'],
                    'source' => $definition['source'],
                    'is_sensitive' => $definition['sensitive'],
                    'requires' => $definition['requires'] === [] ? null : $definition['requires'],
                ]);
                $changed = $isNew || $permission->isDirty();
                $permission->forceFill(['declared_at' => $now])->save();

                if ($changed) {
                    $result[$isNew ? 'created' : 'updated'][] = $name;
                }
            }

            foreach ($existing as $normalized => $permission) {
                if ($permission->source === 'custom' || isset($declared[$permission->name]) || array_key_exists($permission->name, $declared)) {
                    continue;
                }

                if ($this->usage($permission) === ['roles' => 0, 'grants' => 0] && $prune) {
                    $permission->delete();
                    $result['pruned'][] = $permission->name;

                    continue;
                }

                $result['stale'][] = $permission->name;
            }
        });

        $this->changed();

        if ($result['created'] !== [] || $result['updated'] !== [] || $result['pruned'] !== []) {
            $this->audit->record(AuditEvent::PermissionsSynced, $by, payload: [
                'created' => count($result['created']), 'updated' => count($result['updated']), 'pruned' => count($result['pruned']),
                'names' => array_slice([...$result['created'], ...$result['pruned']], 0, 200),
            ]);
        }

        return $result;
    }

    /**
     * Register a custom permission (governed: vault.permissions.manage).
     *
     * @param  array{name?: mixed, label?: mixed, description?: mixed, group?: mixed, scope?: mixed, sensitive?: mixed, requires?: mixed}  $data
     */
    public function create(Model $actor, array $data): Permission
    {
        $this->authority->authorize($actor, VaultPermissions::PermissionsManage);
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';

        if (! Names::isPermission($name)) {
            throw VaultValidationException::make('invalid_permission_name', field: 'name');
        }

        if ($this->find($name) !== null) {
            throw VaultValidationException::make('permission_exists', ['permission' => $name], 'name');
        }

        $permission = new Permission;
        $permission->forceFill(['name' => $name, 'normalized' => Names::normalize($name), 'source' => 'custom', ...$this->attributes($data, $name)])->save();

        $this->changed();
        $this->audit->record(AuditEvent::PermissionCreated, Holder::of($actor), target: 'permission:'.$name);

        return $permission;
    }

    /**
     * Edit a custom permission (governed). Declared permissions belong to code.
     *
     * @param  array{label?: mixed, description?: mixed, group?: mixed, scope?: mixed, sensitive?: mixed, requires?: mixed}  $data
     */
    public function update(Model $actor, Permission $permission, array $data): Permission
    {
        $this->authority->authorize($actor, VaultPermissions::PermissionsManage);

        if ($permission->source !== 'custom') {
            throw VaultAuthorizationException::because('declared_by_code');
        }

        $permission->forceFill($this->attributes($data, $permission->name, $permission));
        $changes = array_keys($permission->getDirty());
        $permission->save();

        $this->changed();
        $this->audit->record(AuditEvent::PermissionUpdated, Holder::of($actor), target: 'permission:'.$permission->name, payload: ['changed' => $changes]);

        return $permission;
    }

    /**
     * Delete a custom permission nobody holds (governed).
     */
    public function delete(Model $actor, Permission $permission): void
    {
        $this->authority->authorize($actor, VaultPermissions::PermissionsManage);

        if ($permission->source !== 'custom') {
            throw VaultAuthorizationException::because('declared_by_code');
        }

        $usage = $this->usage($permission);

        if ($usage['roles'] > 0 || $usage['grants'] > 0) {
            throw VaultValidationException::make('permission_in_use', $usage);
        }

        $name = $permission->name;
        $permission->delete();

        $this->changed();
        $this->audit->record(AuditEvent::PermissionDeleted, Holder::of($actor), target: 'permission:'.$name);
    }

    /**
     * @return array{roles: int, grants: int}
     */
    public function usage(Permission $permission): array
    {
        return [
            'roles' => $permission->roles()->count(),
            'grants' => Grant::query()->where('permission_id', $permission->id)->active()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $name, ?Permission $current = null): array
    {
        $label = is_string($data['label'] ?? null) && trim($data['label']) !== '' ? trim($data['label']) : ($current->label ?? Names::label($name));
        $scope = $data['scope'] ?? ($current->scope ?? Permission::ScopeAny);
        $requires = $data['requires'] ?? ($current?->dependencies() ?? []);
        $group = $data['group'] ?? ($current->group ?? null);
        $description = $data['description'] ?? ($current->description ?? null);

        if (mb_strlen($label) > 150 || (is_string($group) && mb_strlen($group) > 100) || ($group !== null && ! is_string($group)) || ($description !== null && (! is_string($description) || mb_strlen($description) > 2000))) {
            throw VaultValidationException::make('invalid_permission', field: 'label');
        }

        if (! in_array($scope, [Permission::ScopeGlobal, Permission::ScopeTenant, Permission::ScopeAny], true)) {
            throw VaultValidationException::make('invalid_scope', field: 'scope');
        }

        if (! is_array($requires)) {
            throw VaultValidationException::make('invalid_dependencies', field: 'requires');
        }

        $requires = array_values(array_unique(array_filter($requires, 'is_string')));

        foreach ($requires as $required) {
            if ($required === $name || ! $this->evaluator->graph()->knows($required)) {
                throw VaultValidationException::make('invalid_dependencies', field: 'requires');
            }
        }

        return [
            'label' => $label,
            'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
            'group' => is_string($group) && trim($group) !== '' ? trim($group) : null,
            'scope' => $scope,
            'is_sensitive' => ($data['sensitive'] ?? ($current->is_sensitive ?? false)) === true,
            'requires' => $requires === [] ? null : $requires,
        ];
    }

    private function changed(): void
    {
        $this->invalidation->definitions();
    }
}
