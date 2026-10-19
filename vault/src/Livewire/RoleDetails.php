<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Livewire\Concerns\FindsPeople;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

/**
 * One role: details, own and inherited permissions, the roles it inherits
 * and that inherit it, its members (assign, temporary access, revoke), its
 * assignment history and audit trail.
 */
final class RoleDetails extends VaultComponent
{
    use FindsPeople, WithPagination;

    public const array Tabs = ['members', 'permissions', 'history'];

    #[Locked]
    public int $roleId;

    public string $tab = 'members';

    public string $personSearch = '';

    public ?string $personRef = null;

    public string $until = '';

    public string $reason = '';

    public ?int $revoking = null;

    public string $revokeReason = '';

    protected function permission(): ?string
    {
        return VaultPermissions::RolesView;
    }

    public function mount(string $role): void
    {
        $this->roleId = $this->scopedRole($role)->id;
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, self::Tabs, true) ? $this->tab : 'members';
        $this->resetPage();
    }

    public function pick(string $reference): void
    {
        $this->personRef = $this->reference($this->person($reference));
        $this->personSearch = '';
    }

    public function assign(): void
    {
        $role = $this->scopedRole($this->roleId);

        if ($this->personRef === null) {
            $this->addError('person', __('Choose a person.'));

            return;
        }

        $person = $this->person($this->personRef);
        $until = $this->until === '' ? null : Date::parse($this->until)->endOfDay();

        if ($this->attempt(fn () => $this->vault()->assignments()->assignRole($this->actor(), $person, $role, $this->scope(), $until, $this->clean($this->reason)), __(':person now holds ":role".', ['person' => $this->displayName($person), 'role' => $role->label]))) {
            $this->reset('personRef', 'until', 'reason');
        }
    }

    public function confirmRevoke(int $assignment): void
    {
        $this->revoking = $this->scopedAssignment($assignment)->id;
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

        $assignment = $this->scopedAssignment($this->revoking);
        $this->revoking = null;
        $this->attempt(fn () => $this->vault()->assignments()->end($this->actor(), $assignment, $this->clean($this->revokeReason)), __('The role was revoked.'));
        $this->revokeReason = '';
    }

    public function render(): View
    {
        $role = Role::query()->with(['permissions' => static fn ($query) => $query->orderBy('group')->orderBy('name'), 'parents', 'children'])->findOrFail($this->roleId);
        $graph = $this->vault()->evaluator()->graph();
        $inherited = [];

        foreach ($graph->permissionsOf($role->id) as $id => $from) {
            if ($from !== $role->name) {
                $inherited[] = ['name' => $graph->permissions[$id]['name'], 'label' => $graph->permissions[$id]['label'], 'group' => $graph->permissions[$id]['group'], 'from' => $from];
            }
        }

        $members = Assignment::query()->where('role_id', $role->id)->where('tenant', $this->scope())->active()->with('holder', 'grantor')->latest('id')->paginate(20, pageName: 'members');
        $history = Assignment::query()->where('role_id', $role->id)->where('tenant', $this->scope())->with('holder')->latest('id')->paginate(20, pageName: 'history');

        return view('gcego-vault::livewire.role-details', [
            ...$this->shared(),
            'role' => $role,
            'inherited' => collect($inherited)->sortBy('name')->groupBy(static fn (array $permission): string => $permission['group'] ?? ''),
            'own' => $role->permissions->groupBy(static fn ($permission): string => $permission->group ?? ''),
            'members' => $members,
            'history' => $history,
            'audit' => AuditEntry::query()->where('target', 'role:'.$role->name)->where('tenant', $this->scope())->latest('id')->limit(30)->get(),
            'results' => $this->personSearch === '' ? [] : $this->searchPeople($this->personSearch),
            'picked' => $this->personRef === null ? null : $this->person($this->personRef),
            'can' => [
                'assign' => ! $role->isArchived() && $this->may(VaultPermissions::RolesAssign),
                'edit' => $this->may(VaultPermissions::RolesUpdate),
            ],
            'tabs' => self::Tabs,
            'revokingAssignment' => $this->revoking === null ? null : Assignment::query()->with('holder')->find($this->revoking),
            'maxDate' => app(\Gcego\Vault\VaultConfig::class)->maxTemporaryDays === null ? null : now()->addDays(app(\Gcego\Vault\VaultConfig::class)->maxTemporaryDays - 1)->toDateString(),
        ]);
    }

    private function scopedAssignment(int $id): Assignment
    {
        return Assignment::query()->where('role_id', $this->roleId)->where('tenant', $this->scope())->active()->whereKey($id)->first() ?? abort(404);
    }
}
