<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Livewire\Concerns\FindsPeople;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;

/**
 * Everything one person may do here, and why: held and inherited roles,
 * direct grants, every effective permission with its sources, the history of
 * their assignments and grants, and the audit entries about them. Holders of
 * the matching Vault permissions assign roles and grant permissions
 * (optionally temporary) and revoke them, all through the governed API.
 */
final class PersonAccess extends VaultComponent
{
    use FindsPeople;

    public const array Tabs = ['access', 'effective', 'history'];

    #[Locked]
    public string $holder;

    public string $tab = 'access';

    public string $filter = '';

    public string $role = '';

    public string $grant = '';

    public string $until = '';

    public string $reason = '';

    /** @var array{kind: string, id: int}|null */
    public ?array $revoking = null;

    protected function permission(): ?string
    {
        return VaultPermissions::UsersView;
    }

    public function mount(string $holder): void
    {
        $this->holder = $this->reference($this->person($holder));
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, self::Tabs, true) ? $this->tab : 'access';
    }

    public function assignRole(): void
    {
        $person = $this->person($this->holder);
        $role = $this->role === '' ? null : $this->scopedRole($this->role);

        if ($role === null) {
            $this->addError('role', __('Choose a role.'));

            return;
        }

        if ($this->attempt(fn () => $this->vault()->assignments()->assignRole($this->actor(), $person, $role, $this->scope(), $this->untilDate(), $this->clean($this->reason)), __('":role" was given.', ['role' => $role->label]))) {
            $this->reset('role', 'until', 'reason');
        }
    }

    public function grantPermission(): void
    {
        $person = $this->person($this->holder);
        $permission = Permission::query()->where('name', $this->grant)->first();

        if ($permission === null) {
            $this->addError('grant', __('Choose a registered permission.'));

            return;
        }

        if ($this->attempt(fn () => $this->vault()->assignments()->grantPermission($this->actor(), $person, $permission, $this->scope(), $this->untilDate(), $this->clean($this->reason)), __('":permission" was granted.', ['permission' => $permission->name]))) {
            $this->reset('grant', 'until', 'reason');
        }
    }

    public function confirmRevoke(string $kind, int $id): void
    {
        $this->revoking = ['kind' => $kind === 'grant' ? 'grant' : 'role', 'id' => $this->record($kind, $id)->id];
    }

    public function cancelConfirm(): void
    {
        $this->revoking = null;
    }

    public function revoke(): void
    {
        if ($this->revoking === null) {
            return;
        }

        $record = $this->record($this->revoking['kind'], $this->revoking['id']);
        $this->revoking = null;
        $this->attempt(fn () => $this->vault()->assignments()->end($this->actor(), $record, $this->clean($this->reason)), __('Access was revoked.'));
    }

    public function render(): View
    {
        $person = $this->person($this->holder);
        $holder = Holder::of($person);
        $effective = $this->vault()->evaluator()->effective($person, $this->scope(), null);
        $term = mb_strtolower(trim($this->filter));
        $labels = Permission::query()->whereIn('name', $effective->names())->pluck('label', 'name');
        $maxDays = app(VaultConfig::class)->maxTemporaryDays;

        return view('gcego-vault::livewire.person', [
            ...$this->shared(),
            'person' => $person,
            'effective' => $effective,
            'permissions' => collect($effective->names())->filter(static fn (string $name): bool => $term === '' || str_contains(mb_strtolower($name), $term))->map(static fn (string $name): array => ['name' => $name, 'label' => $labels[$name] ?? $name, 'sources' => $effective->sources($name)])->values(),
            'assignments' => Assignment::query()->heldBy($holder->type, $holder->id)->where('tenant', $this->scope())->active()->with('role', 'grantor')->get(),
            'grants' => Grant::query()->heldBy($holder->type, $holder->id)->where('tenant', $this->scope())->active()->with('permission', 'grantor')->get(),
            'history' => Assignment::query()->heldBy($holder->type, $holder->id)->where('tenant', $this->scope())->with('role')->latest('id')->limit(50)->get()
                ->concat(Grant::query()->heldBy($holder->type, $holder->id)->where('tenant', $this->scope())->with('permission')->latest('id')->limit(50)->get())
                ->sortByDesc('created_at')->values(),
            'audit' => AuditEntry::query()->where('subject_type', $holder->type)->where('subject_id', $holder->id)->where('tenant', $this->scope())->latest('id')->limit(30)->get(),
            'roleOptions' => $this->roles()->whereNull('archived_at')->orderBy('label')->get(['id', 'name', 'label']),
            'permissionOptions' => Permission::query()->orderBy('name')->get(['name', 'label', 'scope'])->filter(fn (Permission $permission): bool => $permission->appliesIn($this->scope()))->values(),
            'can' => ['assign' => $this->may(VaultPermissions::RolesAssign), 'grant' => $this->may(VaultPermissions::PermissionsGrant)],
            'tabs' => self::Tabs,
            'maxDate' => $maxDays === null ? null : now()->addDays($maxDays - 1)->toDateString(),
        ]);
    }

    private function untilDate(): ?\Illuminate\Support\Carbon
    {
        return $this->until === '' ? null : Date::parse($this->until)->endOfDay();
    }

    private function record(string $kind, int $id): Assignment|Grant
    {
        $holder = Holder::of($this->person($this->holder));
        $query = $kind === 'grant' ? Grant::query() : Assignment::query();

        return $query->heldBy($holder->type, $holder->id)->where('tenant', $this->scope())->active()->whereKey($id)->first() ?? abort(404);
    }
}
