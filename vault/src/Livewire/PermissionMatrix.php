<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The permission matrix of one role: resources down, abilities across, plus
 * each resource's other permissions. Changes are a draft until saved:
 * the screen shows what would be added and removed, the resulting effective
 * permissions (with inheritance) and missing dependencies, asks for
 * confirmation before sensitive permissions are added, and compares the role
 * with others. Saving is one governed RoleManager::syncPermissions call;
 * the draft is browser state and is never trusted beyond that.
 */
final class PermissionMatrix extends VaultComponent
{
    #[Url(as: 'role', except: null)]
    public ?int $roleId = null;

    /** @var array<int|string, bool> permission id => granted */
    public array $draft = [];

    /** @var list<int> */
    public array $compare = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $confirmingSensitive = false;

    protected function permission(): ?string
    {
        return VaultPermissions::RolesView;
    }

    public function mount(): void
    {
        $this->roleId ??= $this->roles()->whereNull('archived_at')->orderBy('label')->value('id');
        $this->loadDraft();
    }

    public function updatedRoleId(): void
    {
        $this->confirmingSensitive = false;
        $this->compare = array_values(array_diff($this->compare, [$this->roleId]));
        $this->loadDraft();
    }

    public function updatedCompare(): void
    {
        $this->compare = array_values(array_slice(array_map('intval', array_filter($this->compare, 'is_numeric')), 0, 4));
    }

    public function setRow(string $resource, bool $on): void
    {
        foreach ($this->visible()->where('row', $resource) as $permission) {
            $this->draft[$permission->id] = $on;
        }
    }

    public function setColumn(string $ability, bool $on): void
    {
        foreach ($this->visible()->where('ability', $ability) as $permission) {
            $this->draft[$permission->id] = $on;
        }
    }

    public function discard(): void
    {
        $this->confirmingSensitive = false;
        $this->loadDraft();
    }

    public function cancelConfirm(): void
    {
        $this->confirmingSensitive = false;
    }

    public function save(): void
    {
        $role = $this->current() ?? abort(404);
        [$added] = $this->diff($role);

        if (! $this->confirmingSensitive && Permission::query()->whereIn('name', $added)->where('is_sensitive', true)->exists()) {
            $this->confirmingSensitive = true;

            return;
        }

        $this->confirmingSensitive = false;
        $names = Permission::query()->whereKey($this->granted())->pluck('name')->all();

        if ($this->attempt(fn () => $this->vault()->roles()->syncPermissions($this->actor(), $role, $names), __('The permissions of ":role" were saved.', ['role' => $role->label]))) {
            $this->loadDraft();
        }
    }

    public function render(): View
    {
        $role = $this->current();
        $abilities = app(VaultConfig::class)->abilities;
        $visible = $this->visible();
        $rows = $visible->groupBy('row')->map(static fn (Collection $permissions): array => [
            'group' => $permissions->first()->group,
            'cells' => $permissions->filter(static fn (Permission $permission): bool => in_array($permission->ability, $abilities, true))->keyBy('ability'),
            'other' => $permissions->reject(static fn (Permission $permission): bool => in_array($permission->ability, $abilities, true))->values(),
        ])->sortBy(static fn (array $row, string $name): string => ($row['group'] ?? '~').'|'.$name);
        [$added, $removed] = $role === null ? [[], []] : $this->diff($role);
        $graph = $this->vault()->evaluator()->graph();
        $inherited = [];

        foreach ($role === null ? [] : $role->parents()->pluck('gcego_vault_roles.id')->all() as $parent) {
            foreach ($graph->permissionsOf((int) $parent) as $id => $from) {
                $inherited[$id] = $from;
            }
        }

        $granted = array_fill_keys($this->granted(), true);
        $effective = collect(array_keys($granted + $inherited))->map(static fn (int $id): ?string => $graph->permissions[$id]['name'] ?? null)->filter()->sort()->values();
        $missing = [];

        foreach (array_keys($granted) as $id) {
            foreach ($graph->permissions[$id]['requires'] ?? [] as $required) {
                if (! $effective->contains($required)) {
                    $missing[$required] = true;
                }
            }
        }

        $comparison = [];

        foreach (Role::query()->whereKey($this->compare)->availableIn($this->scope())->get() as $other) {
            $comparison[$other->label] = array_flip(array_map(static fn (int $id): string => $graph->permissions[$id]['name'], array_keys($graph->permissionsOf($other->id))));
        }

        return view('gcego-vault::livewire.matrix', [
            ...$this->shared(),
            'role' => $role,
            'roles' => $this->roles()->whereNull('archived_at')->orderBy('label')->get(['id', 'label', 'name']),
            'abilities' => $abilities,
            'rows' => $rows,
            'added' => $added,
            'removed' => $removed,
            'inherited' => $inherited,
            'effective' => $effective,
            'missing' => array_keys($missing),
            'comparison' => $comparison,
            'locked' => $role !== null && $this->vault()->roles()->isLocked($role),
            'canEdit' => $role !== null && ! $role->is_super && $this->may(VaultPermissions::RolesUpdate),
            'sensitiveAdded' => $this->confirmingSensitive ? Permission::query()->whereIn('name', $added)->where('is_sensitive', true)->pluck('label')->all() : [],
        ]);
    }

    private function current(): ?Role
    {
        return $this->roleId === null ? null : Role::query()->availableIn($this->scope())->whereKey($this->roleId)->first();
    }

    private function loadDraft(): void
    {
        $role = $this->current();
        $this->draft = [];

        if ($role === null) {
            $this->roleId = null;

            return;
        }

        foreach ($role->permissions()->pluck('gcego_vault_permissions.id')->all() as $id) {
            $this->draft[(int) $id] = true;
        }
    }

    /**
     * @return list<int>
     */
    private function granted(): array
    {
        $known = Permission::query()->pluck('id')->all();

        return array_values(array_intersect(array_map('intval', array_keys(array_filter($this->draft))), $known));
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function diff(Role $role): array
    {
        $current = $role->permissions()->pluck('name')->all();
        $draft = Permission::query()->whereKey($this->granted())->pluck('name')->all();

        return [array_values(array_diff($draft, $current)), array_values(array_diff($current, $draft))];
    }

    /**
     * Permissions shown (scope-compatible, matching the search), each with its matrix row.
     *
     * @return Collection<int, Permission>
     */
    private function visible(): Collection
    {
        $tenantRole = ($this->current()->tenant ?? '') !== '';
        $term = mb_strtolower(trim($this->search));

        return Permission::query()->orderBy('name')->get(['id', 'name', 'label', 'group', 'resource', 'ability', 'scope', 'is_sensitive', 'requires'])
            ->reject(static fn (Permission $permission): bool => $tenantRole && $permission->scope === Permission::ScopeGlobal)
            ->filter(static fn (Permission $permission): bool => $term === '' || str_contains(mb_strtolower($permission->name.' '.$permission->label.' '.$permission->group), $term))
            ->each(static function (Permission $permission): void {
                $permission->setAttribute('row', $permission->resource ?? ($permission->group ?? substr($permission->name, 0, (int) strpos($permission->name, '.'))));
            })->values();
    }
}
