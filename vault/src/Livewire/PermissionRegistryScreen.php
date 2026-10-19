<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Models\Permission;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * The permission registry: every registered permission with its group,
 * scope, source, dependencies, sensitivity and how many roles grant it.
 * Holders of vault.permissions.manage register custom permissions and edit
 * or delete them; declared ones belong to code.
 */
final class PermissionRegistryScreen extends VaultComponent
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $source = '';

    public ?int $editing = null;

    public bool $creating = false;

    /** @var array{name: string, label: string, description: string, group: string, scope: string, sensitive: bool, requires: string} */
    public array $form = ['name' => '', 'label' => '', 'description' => '', 'group' => '', 'scope' => 'any', 'sensitive' => false, 'requires' => ''];

    public ?int $deleting = null;

    protected function permission(): ?string
    {
        return VaultPermissions::PermissionsView;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'group', 'source'], true)) {
            $this->search = mb_substr($this->search, 0, 100);
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->permit(VaultPermissions::PermissionsManage);
        $this->reset('form', 'editing');
        $this->creating = true;
    }

    public function edit(int $id): void
    {
        $this->permit(VaultPermissions::PermissionsManage);
        $permission = $this->custom($id);
        $this->creating = false;
        $this->editing = $permission->id;
        $this->form = ['name' => $permission->name, 'label' => $permission->label, 'description' => (string) $permission->description, 'group' => (string) $permission->group, 'scope' => $permission->scope, 'sensitive' => $permission->is_sensitive, 'requires' => implode(', ', $permission->dependencies())];
    }

    public function cancel(): void
    {
        $this->reset('form', 'editing', 'creating');
    }

    public function save(): void
    {
        $data = [
            'name' => trim((string) $this->form['name']),
            'label' => trim((string) $this->form['label']),
            'description' => $this->clean((string) $this->form['description'], 2000),
            'group' => $this->clean((string) $this->form['group'], 100),
            'scope' => (string) $this->form['scope'],
            'sensitive' => (bool) $this->form['sensitive'],
            'requires' => array_values(array_filter(array_map('trim', explode(',', (string) $this->form['requires'])))),
        ];
        $registry = $this->vault()->permissions();
        $done = $this->editing === null
            ? $this->attempt(fn () => $registry->create($this->actor(), $data), __('The permission ":name" was registered. Nobody holds it yet.', ['name' => $data['name']]))
            : $this->attempt(fn () => $registry->update($this->actor(), $this->custom($this->editing), $data), __('The permission was updated.'));

        if ($done) {
            $this->cancel();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleting = $this->custom($id)->id;
    }

    public function cancelConfirm(): void
    {
        $this->deleting = null;
    }

    public function delete(): void
    {
        if ($this->deleting === null) {
            return;
        }

        $permission = $this->custom($this->deleting);
        $this->deleting = null;
        $this->attempt(fn () => $this->vault()->permissions()->delete($this->actor(), $permission), __('The permission was deleted.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $permissions = Permission::query()->withCount('roles')
            ->when($this->group !== '', fn ($query) => $query->where('group', $this->group))
            ->when(in_array($this->source, ['config', 'discovered', 'custom'], true), fn ($query) => $query->where('source', $this->source))
            ->when($term !== '', static fn ($query) => $query->where(static fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('label', 'like', '%'.$term.'%')))
            ->orderBy('group')->orderBy('name')->paginate(50);
        $latest = Permission::query()->max('declared_at');

        return view('gcego-vault::livewire.permissions', [
            ...$this->shared(),
            'permissions' => $permissions,
            'groups' => Permission::query()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group')->all(),
            'latestDeclared' => $latest,
            'canManage' => $this->may(VaultPermissions::PermissionsManage),
            'deletingPermission' => $this->deleting === null ? null : Permission::query()->find($this->deleting),
        ]);
    }

    private function custom(int $id): Permission
    {
        return Permission::query()->where('source', 'custom')->whereKey($id)->first() ?? abort(404);
    }
}
