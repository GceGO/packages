<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;

/**
 * One's own access requests: ask for a role or a permission (optionally
 * until a date) with a reason, follow the approvals, withdraw a pending one.
 * Open to anyone signed in when requests are enabled; asking grants nothing.
 */
final class AccessRequestsScreen extends VaultComponent
{
    public string $kind = 'role';

    public string $role = '';

    public string $requested = '';

    public string $until = '';

    public string $reason = '';

    protected function permission(): ?string
    {
        if (! app(VaultConfig::class)->requests) {
            throw VaultAuthorizationException::because('requests_disabled');
        }

        return null;
    }

    public function submit(): void
    {
        $data = [
            'role' => $this->kind === 'role' && $this->role !== '' ? $this->scopedRole($this->role)->name : null,
            'permission' => $this->kind === 'permission' ? trim($this->requested) : null,
            'tenant' => $this->scope(),
            'reason' => $this->reason,
            'until' => $this->until === '' ? null : Date::parse($this->until)->endOfDay(),
        ];

        if ($this->attempt(fn () => $this->vault()->requests()->submit($this->actor(), $data), __('Your request was sent. It grants nothing until it is approved.'))) {
            $this->reset('role', 'requested', 'until', 'reason');
        }
    }

    public function cancelRequest(int $id): void
    {
        $request = $this->vault()->requests()->of($this->actor())->whereKey($id)->first() ?? abort(404);
        $this->attempt(fn () => $this->vault()->requests()->cancel($this->actor(), $request), __('The request was withdrawn.'));
    }

    public function render(): View
    {
        $config = app(VaultConfig::class);

        return view('gcego-vault::livewire.requests', [
            ...$this->shared(),
            'requests' => $this->vault()->requests()->of($this->actor())->limit(50)->get(),
            'roleOptions' => $this->roles()->whereNull('archived_at')->where('is_super', false)->orderBy('label')->get(['id', 'label', 'description']),
            'permissionOptions' => Permission::query()->orderBy('name')->get(['name', 'label', 'scope'])->filter(fn (Permission $permission): bool => $permission->appliesIn($this->scope()))->values(),
            'me' => \Gcego\Vault\Support\Holder::of($this->actor()),
            'maxDate' => $config->maxTemporaryDays === null ? null : now()->addDays($config->maxTemporaryDays - 1)->toDateString(),
            'approvals' => ['normal' => $config->approvals, 'sensitive' => $config->sensitiveApprovals],
        ]);
    }
}
