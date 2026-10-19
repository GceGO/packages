<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Panels\PanelManager;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Create or edit a role: details, inherited roles and permissions, saved as
 * one governed change (all or nothing). Permissions a role would get from
 * its inherited roles are shown as such; missing dependencies are named
 * before saving and refused by RoleManager regardless.
 */
final class RoleEditor extends VaultComponent
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    public string $label = '';

    public string $description = '';

    public string $group = '';

    public string $panel = '';

    /** @var list<string> */
    public array $permissions = [];

    /** @var list<string> */
    public array $inherits = [];

    public string $filter = '';

    /**
     * boot() runs before mount() knows whether this is a new role, so it only
     * requires viewing roles; mount() and every update (hydrate) require the
     * specific permission.
     */
    protected function permission(): ?string
    {
        return VaultPermissions::RolesView;
    }

    public function hydrate(): void
    {
        $this->permit($this->roleId === null ? VaultPermissions::RolesCreate : VaultPermissions::RolesUpdate);
    }

    public function mount(?string $role = null): void
    {
        if ($role === null) {
            $this->permit(VaultPermissions::RolesCreate);

            return;
        }

        $model = $this->scopedRole($role);
        $this->roleId = $model->id;
        $this->permit(VaultPermissions::RolesUpdate);
        $this->name = $model->name;
        $this->label = $model->label;
        $this->description = (string) $model->description;
        $this->group = (string) $model->group;
        $this->panel = (string) $model->panel;
        $this->permissions = $model->permissions()->orderBy('name')->pluck('name')->all();
        $this->inherits = $model->parents()->orderBy('name')->pluck('name')->all();
    }

    public function toggleGroup(string $group, bool $on): void
    {
        $names = $this->catalog()->where('group', $group === '' ? null : $group)->pluck('name')->all();
        $this->permissions = $on ? array_values(array_unique([...$this->permissions, ...$names])) : array_values(array_diff($this->permissions, $names));
    }

    public function addDependencies(): void
    {
        $this->permissions = array_values(array_unique([...$this->permissions, ...$this->missing()]));
    }

    public function save(): mixed
    {
        $data = [
            'name' => trim($this->name),
            'label' => trim($this->label),
            'description' => $this->clean($this->description, 2000),
            'group' => $this->clean($this->group, 100),
            'panel' => $this->clean($this->panel, 64),
        ];
        $permissions = array_values(array_filter($this->permissions, 'is_string'));
        $inherits = array_values(array_filter($this->inherits, 'is_string'));
        $saved = null;

        $done = $this->attempt(function () use ($data, $permissions, $inherits, &$saved): void {
            $roles = $this->vault()->roles();

            if ($this->roleId === null) {
                $saved = $roles->create($this->actor(), [...$data, 'permissions' => $permissions, 'inherits' => $inherits], $this->scope());

                return;
            }

            $role = $this->scopedRole($this->roleId);
            $roles->update($this->actor(), $role, $data);

            if (! $roles->isLocked($role)) {
                $roles->syncInheritance($this->actor(), $role, $inherits);
                $roles->syncPermissions($this->actor(), $role, $permissions);
            }

            $saved = $role;
        }, __('The role was saved.'), transaction: true);

        return $done && $saved instanceof Role ? $this->redirect($this->url('roles.show', ['role' => $saved->id])) : null;
    }

    public function render(): View
    {
        $role = $this->roleId === null ? null : Role::query()->find($this->roleId);
        $catalog = $this->catalog();
        $filter = mb_strtolower(trim($this->filter));
        $graph = $this->vault()->evaluator()->graph();
        $inherited = [];

        foreach ($this->inherits as $name) {
            $parent = $this->vault()->roles()->find($name, $this->scope());

            foreach ($parent === null ? [] : $graph->permissionsOf($parent->id) as $id => $from) {
                $inherited[$graph->permissions[$id]['name']] = $from;
            }
        }

        return view('gcego-vault::livewire.role-editor', [
            ...$this->shared(),
            'role' => $role,
            'locked' => $role !== null && $this->vault()->roles()->isLocked($role),
            'groups' => $catalog->filter(static fn (Permission $permission): bool => $filter === '' || str_contains(mb_strtolower($permission->name.' '.$permission->label), $filter))->groupBy(static fn (Permission $permission): string => $permission->group ?? ''),
            'inherited' => $inherited,
            'missing' => $this->missing($inherited),
            'parents' => $this->roles()->whereNull('archived_at')->where('is_super', false)->when($this->roleId !== null, fn ($query) => $query->whereKeyNot($this->roleId))->orderBy('label')->get(['id', 'name', 'label']),
            'panels' => $this->panels(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    private function catalog(): \Illuminate\Support\Collection
    {
        $tenantRole = $this->scope() !== '';

        return Permission::query()->orderBy('group')->orderBy('name')->get(['id', 'name', 'label', 'group', 'scope', 'is_sensitive', 'requires'])
            ->reject(static fn (Permission $permission): bool => $tenantRole && $permission->scope === Permission::ScopeGlobal)->values();
    }

    /**
     * Dependencies of the chosen permissions that neither the role nor its inherited roles grant.
     *
     * @param  array<string, string>|null  $inherited
     * @return list<string>
     */
    private function missing(?array $inherited = null): array
    {
        $inherited ??= [];
        $chosen = array_fill_keys($this->permissions, true);
        $missing = [];

        foreach (Permission::query()->whereIn('name', $this->permissions)->get(['name', 'requires']) as $permission) {
            foreach ($permission->dependencies() as $required) {
                if (! isset($chosen[$required]) && ! isset($inherited[$required])) {
                    $missing[$required] = true;
                }
            }
        }

        return array_keys($missing);
    }

    /**
     * @return list<string>
     */
    private function panels(): array
    {
        try {
            return app(PanelManager::class)->names();
        } catch (Throwable) {
            return [];
        }
    }
}
