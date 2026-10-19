<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Roles overview: search, filter, sort and group the roles of the current
 * scope; archive, restore, duplicate and delete them; export and import
 * role definitions. Every action is decided again by RoleManager.
 */
final class RolesIndex extends VaultComponent
{
    use WithFileUploads, WithPagination;

    public const array Sorts = ['name', 'label', 'permissions_count', 'holders_count', 'updated_at'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public string $direction = 'asc';

    /** @var array{action: string, id: int}|null */
    public ?array $confirming = null;

    public ?int $duplicating = null;

    public string $duplicateName = '';

    public mixed $import = null;

    public bool $overwrite = false;

    protected function permission(): ?string
    {
        return VaultPermissions::RolesView;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'group', 'sort'], true)) {
            $this->search = mb_substr($this->search, 0, 100);
            $this->status = in_array($this->status, ['active', 'archived', 'all'], true) ? $this->status : 'active';
            $this->sort = in_array($this->sort, self::Sorts, true) ? $this->sort : 'name';
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::Sorts, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function confirm(string $action, int $id): void
    {
        $this->confirming = in_array($action, ['archive', 'delete'], true) ? ['action' => $action, 'id' => $this->scopedRole($id)->id] : null;
    }

    public function cancelConfirm(): void
    {
        $this->confirming = null;
    }

    public function confirmed(): void
    {
        if ($this->confirming === null) {
            return;
        }

        $role = $this->scopedRole($this->confirming['id']);
        $action = $this->confirming['action'];
        $this->confirming = null;

        $action === 'archive'
            ? $this->attempt(fn () => $this->vault()->roles()->archive($this->actor(), $role), __('":role" was archived. Its holders no longer get its permissions.', ['role' => $role->label]))
            : $this->attempt(fn () => $this->vault()->roles()->delete($this->actor(), $role), __('":role" was deleted.', ['role' => $role->label]));
    }

    public function restore(int $id): void
    {
        $role = $this->scopedRole($id);
        $this->attempt(fn () => $this->vault()->roles()->restore($this->actor(), $role), __('":role" was restored.', ['role' => $role->label]));
    }

    public function startDuplicate(int $id): void
    {
        $role = $this->scopedRole($id);
        $this->duplicating = $role->id;
        $this->duplicateName = mb_substr($role->name.'-copy', 0, 100);
    }

    public function duplicate(): void
    {
        if ($this->duplicating === null) {
            return;
        }

        $role = $this->scopedRole($this->duplicating);

        if ($this->attempt(fn () => $this->vault()->roles()->duplicate($this->actor(), $role, $this->duplicateName), __('A copy of ":role" was created.', ['role' => $role->label]))) {
            $this->duplicating = null;
        }
    }

    public function cancelDuplicate(): void
    {
        $this->duplicating = null;
    }

    public function export(): ?StreamedResponse
    {
        $data = null;

        if (! $this->attempt(function () use (&$data): void {
            $data = $this->vault()->roles()->export($this->actor(), null, $this->scope());
        }, __('Role definitions exported.'))) {
            return null;
        }

        return response()->streamDownload(static function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, 'vault-roles-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    public function importRoles(): void
    {
        $this->validate(['import' => ['required', 'file', 'max:1024', 'mimetypes:application/json,text/plain']]);

        $file = $this->import;
        $payload = $file instanceof TemporaryUploadedFile ? json_decode((string) file_get_contents($file->getRealPath()), true) : null;

        $result = null;
        $this->attempt(function () use ($payload, &$result): void {
            if (! is_array($payload)) {
                throw VaultValidationException::make('invalid_import', field: 'import');
            }

            $result = $this->vault()->roles()->import($this->actor(), $payload, $this->scope(), $this->overwrite);
        }, __('Import complete.'));

        if (is_array($result)) {
            $this->feedback = ['tone' => 'success', 'message' => __('Import complete: :created created, :updated updated, :skipped skipped.', ['created' => count($result['created']), 'updated' => count($result['updated']), 'skipped' => count($result['skipped'])])];
        }

        $this->import = null;
    }

    public function render(): View
    {
        $roles = $this->roles()
            ->withCount(['permissions', 'assignments as holders_count' => fn ($query) => $query->where('tenant', $this->scope())->active()])
            ->with('parents:id,name,label')
            ->when($this->status === 'active', static fn ($query) => $query->whereNull('archived_at'))
            ->when($this->status === 'archived', static fn ($query) => $query->whereNotNull('archived_at'))
            ->when($this->group !== '', fn ($query) => $query->where('group', $this->group))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%')->orWhere('label', 'like', '%'.trim($this->search).'%')->orWhere('description', 'like', '%'.trim($this->search).'%')))
            ->orderBy(in_array($this->sort, self::Sorts, true) ? $this->sort : 'name', $this->direction === 'desc' ? 'desc' : 'asc')
            ->paginate(25);

        return view('gcego-vault::livewire.roles', [
            ...$this->shared(),
            'roles' => $roles,
            'groups' => $this->roles()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group')->all(),
            'can' => [
                'create' => $this->may(VaultPermissions::RolesCreate),
                'delete' => $this->may(VaultPermissions::RolesDelete),
                'export' => $this->may(VaultPermissions::RolesExport),
                'import' => $this->may(VaultPermissions::RolesImport),
            ],
            'confirmRole' => $this->confirming === null ? null : Role::query()->find($this->confirming['id']),
        ]);
    }
}
