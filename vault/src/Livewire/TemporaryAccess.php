<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;

/**
 * Temporary access in this scope: active roles and grants with an end date,
 * soonest first, and what expired in the last 30 days. Expired access has
 * already stopped working; gcego:vault:expire only closes the records.
 */
final class TemporaryAccess extends VaultComponent
{
    /** @var array{kind: string, id: int}|null */
    public ?array $revoking = null;

    protected function permission(): ?string
    {
        return VaultPermissions::UsersView;
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
        $this->attempt(fn () => $this->vault()->assignments()->end($this->actor(), $record, __('Ended early')), __('The temporary access was ended.'));
    }

    public function render(): View
    {
        $active = Assignment::query()->where('tenant', $this->scope())->whereNotNull('expires_at')->active()->with('role', 'holder')->get()
            ->concat(Grant::query()->where('tenant', $this->scope())->whereNotNull('expires_at')->active()->with('permission', 'holder')->get())
            ->sortBy('expires_at')->values();
        $since = Date::now()->subDays(30);
        $expired = Assignment::query()->where('tenant', $this->scope())->whereNotNull('expires_at')->where('expires_at', '<=', Date::now())->where('expires_at', '>=', $since)->whereNull('revoked_by_id')->with('role', 'holder')->get()
            ->concat(Grant::query()->where('tenant', $this->scope())->whereNotNull('expires_at')->where('expires_at', '<=', Date::now())->where('expires_at', '>=', $since)->whereNull('revoked_by_id')->with('permission', 'holder')->get())
            ->sortByDesc('expires_at')->values();

        return view('gcego-vault::livewire.temporary', [...$this->shared(), 'active' => $active, 'expired' => $expired, 'canEnd' => $this->may(VaultPermissions::RolesAssign) || $this->may(VaultPermissions::PermissionsGrant)]);
    }

    private function record(string $kind, int $id): Assignment|Grant
    {
        return ($kind === 'grant' ? Grant::query() : Assignment::query())->where('tenant', $this->scope())->whereNotNull('expires_at')->active()->whereKey($id)->first() ?? abort(404);
    }
}
