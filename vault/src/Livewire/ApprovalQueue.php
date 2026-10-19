<?php

declare(strict_types=1);

namespace Gcego\Vault\Livewire;

use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Permissions\VaultPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\WithPagination;

/**
 * Requests waiting for this reviewer: never their own (as requester or
 * beneficiary), never ones they already reviewed, only in scopes where they
 * hold vault.requests.review. Approving re-checks every escalation rule.
 */
final class ApprovalQueue extends VaultComponent
{
    use WithPagination;

    /** @var array<int|string, string> request id => note */
    public array $notes = [];

    /** @var array<int|string, string> request id => approved until */
    public array $untils = [];

    protected function permission(): ?string
    {
        return VaultPermissions::RequestsReview;
    }

    public function approve(int $id): void
    {
        $request = $this->reviewable($id);
        $until = ($this->untils[$id] ?? '') === '' ? null : Date::parse($this->untils[$id])->endOfDay();
        $this->attempt(fn () => $this->vault()->requests()->approve($this->actor(), $request, $this->clean($this->notes[$id] ?? null), $until), __('Your approval was recorded.'));
    }

    public function reject(int $id): void
    {
        $request = $this->reviewable($id);
        $this->attempt(fn () => $this->vault()->requests()->reject($this->actor(), $request, (string) ($this->notes[$id] ?? '')), __('The request was rejected.'));
    }

    public function render(): View
    {
        return view('gcego-vault::livewire.approvals', [...$this->shared(), 'requests' => $this->vault()->requests()->reviewableBy($this->actor())->with('requester', 'subject')->paginate(20)]);
    }

    private function reviewable(int $id): AccessRequest
    {
        return $this->vault()->requests()->reviewableBy($this->actor())->whereKey($id)->first() ?? abort(404);
    }
}
