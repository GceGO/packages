<?php

declare(strict_types=1);

namespace Gcego\Vault\Governance;

use DateTimeInterface;
use Gcego\Vault\Assignments\AssignmentManager;
use Gcego\Vault\Auditing\AuditLog;
use Gcego\Vault\Authorization\PermissionEvaluator;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Enums\GrantSource;
use Gcego\Vault\Enums\RequestStatus;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Models\Approval;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\PermissionRegistry;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Roles\RoleManager;
use Gcego\Vault\Support\Context;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\Tenancy\Scope;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;

/**
 * Access requests and their approval.
 *
 * - Anyone signed in may ask for a role or a permission for themselves; asking
 *   for someone else needs the authority to give it (vault.roles.assign or
 *   vault.permissions.grant), and then still needs other people's approval.
 * - A request never grants anything by itself. It is approved only when
 *   governance.approvals (sensitive_approvals for sensitive access) distinct
 *   reviewers approve; one rejection rejects it.
 * - Separation of duties: neither the requester nor the beneficiary may
 *   review. Each reviewer needs vault.requests.review where the access
 *   applies and must be allowed to give that access themselves (every
 *   EscalationGuard rule), so approving can never escalate anyone.
 * - Finalizing is compare-and-set on the pending status inside a
 *   transaction: two concurrent final approvals grant once.
 */
final readonly class AccessRequests
{
    public function __construct(
        private RoleManager $roles,
        private PermissionRegistry $permissions,
        private AssignmentManager $assignments,
        private EscalationGuard $guard,
        private Authority $authority,
        private PermissionEvaluator $evaluator,
        private AuditLog $audit,
        private Context $context,
        private VaultConfig $config,
    ) {}

    /**
     * @param  array{role?: mixed, permission?: mixed, tenant?: mixed, reason?: mixed, until?: mixed}  $data
     */
    public function submit(Model $requester, array $data, ?Model $subject = null): AccessRequest
    {
        if (! $this->config->requests) {
            throw VaultAuthorizationException::because('requests_disabled');
        }

        $subject ??= $requester;
        $tenant = is_string($data['tenant'] ?? null) ? $data['tenant'] : Scope::Global;
        $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
        $until = $data['until'] ?? null;
        [$role, $permission] = $this->item($data, $tenant);
        $onBehalf = ! Holder::of($requester)->is(Holder::of($subject));

        if (! Scope::isValid($tenant)) {
            throw VaultValidationException::make('invalid_scope', field: 'tenant');
        }

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw VaultValidationException::make('reason_required', field: 'reason');
        }

        if ($until !== null && (! $until instanceof DateTimeInterface || ! Date::instance($until)->isFuture() || ($this->config->maxTemporaryDays !== null && Date::instance($until)->greaterThan(Date::now()->addDays($this->config->maxTemporaryDays))))) {
            throw VaultValidationException::make('invalid_until', ['days' => $this->config->maxTemporaryDays ?? 0], 'until');
        }

        if ($onBehalf) {
            $this->authority->authorize($requester, $role !== null ? VaultPermissions::RolesAssign : VaultPermissions::PermissionsGrant, $tenant);
        }

        if (! $subject instanceof Authenticatable || ! $this->context->isMember($subject, $tenant)) {
            throw VaultAuthorizationException::because('not_member');
        }

        if ($this->holds($subject, $role, $permission, $tenant)) {
            throw VaultValidationException::make('already_held', field: $role !== null ? 'role' : 'permission');
        }

        $requesterHolder = Holder::of($requester);

        if (AccessRequest::query()->where('requester_type', $requesterHolder->type)->where('requester_id', $requesterHolder->id)->where('status', RequestStatus::Pending)->count() >= $this->config->maxPending) {
            throw VaultValidationException::make('too_many_requests', ['count' => $this->config->maxPending]);
        }

        $sensitive = $role !== null ? $this->guard->isSensitiveRole($role) : (bool) $permission?->is_sensitive;
        $subjectHolder = Holder::of($subject);
        $item = $role !== null ? 'r'.$role->id : 'p'.$permission?->id;
        $request = new AccessRequest;
        $request->forceFill([
            'requester_type' => $requesterHolder->type,
            'requester_id' => $requesterHolder->id,
            'subject_type' => $subjectHolder->type,
            'subject_id' => $subjectHolder->id,
            'role_id' => $role?->id,
            'permission_id' => $permission?->id,
            'tenant' => $tenant,
            'reason' => $reason,
            'requested_until' => $until,
            'status' => RequestStatus::Pending,
            'approvals_required' => $sensitive ? $this->config->sensitiveApprovals : $this->config->approvals,
            'is_sensitive' => $sensitive,
            'pending_key' => sha1($subjectHolder->key().'|'.$item.'|'.$tenant),
        ]);

        try {
            $request->save();
        } catch (QueryException) {
            throw VaultValidationException::make('request_pending');
        }

        $this->audit->record(AuditEvent::RequestSubmitted, $requesterHolder, $subjectHolder, $request->item(), $tenant, $reason, ['request' => $request->id, 'approvals_required' => $request->approvals_required]);

        return $request;
    }

    /**
     * Record an approval; with enough approvals, grant the access (until $until, or what was asked for).
     */
    public function approve(Model $reviewer, AccessRequest $request, ?string $reason = null, ?DateTimeInterface $until = null): AccessRequest
    {
        $subject = $this->reviewable($reviewer, $request);
        $role = $request->role;
        $permission = $request->permission;

        if ($role !== null) {
            $this->guard->assignRole($reviewer, $subject, $role, $request->tenant, approved: true);
        } elseif ($permission !== null) {
            $this->guard->grantPermission($reviewer, $subject, $permission, $request->tenant, approved: true);
        } else {
            throw VaultValidationException::make('unknown_item');
        }

        $until ??= $request->requested_until;
        $reviewerHolder = Holder::of($reviewer);
        $finalized = false;

        $request->getConnection()->transaction(function () use ($request, $reviewerHolder, $reason, $until, $subject, $role, $permission, &$finalized): void {
            $this->vote($request, $reviewerHolder, Approval::Approve, $reason);
            $approvals = $request->approvals()->where('decision', Approval::Approve)->count();

            if ($approvals < $request->approvals_required) {
                return;
            }

            $won = AccessRequest::query()->whereKey($request->id)->where('status', RequestStatus::Pending)->update([
                'status' => RequestStatus::Approved->value, 'pending_key' => null, 'approved_until' => $until, 'decided_at' => Date::now(),
                'decided_by_type' => $reviewerHolder->type, 'decided_by_id' => $reviewerHolder->id, 'decision_reason' => $reason === null ? null : mb_substr($reason, 0, 500),
            ]);

            if ($won === 0) {
                return;
            }

            $note = 'Request #'.$request->id.($reason === null || trim($reason) === '' ? '' : ': '.trim($reason));
            $granted = $role !== null
                ? $this->assignments->assignTrusted($subject, $role, $request->tenant, $until, $note, GrantSource::Request, $reviewerHolder, $request->id)
                : $this->assignments->grantTrusted($subject, $permission ?? throw VaultValidationException::make('unknown_item'), $request->tenant, $until, $note, GrantSource::Request, $reviewerHolder, $request->id);

            AccessRequest::query()->whereKey($request->id)->update($granted instanceof Assignment ? ['assignment_id' => $granted->id] : ['grant_id' => $granted->id]);
            $finalized = true;
        });

        $request->refresh();
        $this->audit->record($finalized ? AuditEvent::RequestApproved : AuditEvent::RequestReviewed, $reviewerHolder, Holder::of($subject), $request->item(), $request->tenant, $reason, ['request' => $request->id, 'decision' => 'approve']);

        return $request;
    }

    public function reject(Model $reviewer, AccessRequest $request, string $reason): AccessRequest
    {
        $subject = $this->reviewable($reviewer, $request);

        if (mb_strlen(trim($reason)) < 3) {
            throw VaultValidationException::make('reason_required', field: 'reason');
        }

        $reviewerHolder = Holder::of($reviewer);

        $request->getConnection()->transaction(function () use ($request, $reviewerHolder, $reason): void {
            $this->vote($request, $reviewerHolder, Approval::Reject, $reason);
            AccessRequest::query()->whereKey($request->id)->where('status', RequestStatus::Pending)->update([
                'status' => RequestStatus::Rejected->value, 'pending_key' => null, 'decided_at' => Date::now(),
                'decided_by_type' => $reviewerHolder->type, 'decided_by_id' => $reviewerHolder->id, 'decision_reason' => mb_substr(trim($reason), 0, 500),
            ]);
        });

        $request->refresh();
        $this->audit->record(AuditEvent::RequestRejected, $reviewerHolder, Holder::of($subject), $request->item(), $request->tenant, $reason, ['request' => $request->id]);

        return $request;
    }

    /**
     * Withdraw one's own pending request.
     */
    public function cancel(Model $requester, AccessRequest $request): AccessRequest
    {
        $holder = Holder::of($requester);

        if ($request->requester_type !== $holder->type || $request->requester_id !== $holder->id) {
            throw VaultAuthorizationException::because('not_your_request');
        }

        $updated = AccessRequest::query()->whereKey($request->id)->where('status', RequestStatus::Pending)->update(['status' => RequestStatus::Cancelled->value, 'pending_key' => null, 'decided_at' => Date::now()]);

        if ($updated === 0) {
            throw VaultValidationException::make('request_closed');
        }

        $request->refresh();
        $this->audit->record(AuditEvent::RequestCancelled, $holder, new Holder($request->subject_type, $request->subject_id), $request->item(), $request->tenant, payload: ['request' => $request->id]);

        return $request;
    }

    /**
     * Pending requests this reviewer may decide: in scopes where they hold vault.requests.review,
     * never their own (as requester or beneficiary), never ones they already reviewed.
     *
     * @return Builder<AccessRequest>
     */
    public function reviewableBy(Model $reviewer): Builder
    {
        $holder = Holder::of($reviewer);
        $scopes = array_values(array_filter(
            AccessRequest::query()->where('status', RequestStatus::Pending)->distinct()->pluck('tenant')->all(),
            fn (string $tenant): bool => $this->authority->holds($reviewer, VaultPermissions::RequestsReview, $tenant),
        ));

        return AccessRequest::query()
            ->where('status', RequestStatus::Pending)
            ->whereIn('tenant', $scopes === [] ? ['__none__'] : $scopes)
            ->whereNot(static fn (Builder $query): Builder => $query->where('requester_type', $holder->type)->where('requester_id', $holder->id))
            ->whereNot(static fn (Builder $query): Builder => $query->where('subject_type', $holder->type)->where('subject_id', $holder->id))
            ->whereDoesntHave('approvals', static fn (Builder $query): Builder => $query->where('approver_type', $holder->type)->where('approver_id', $holder->id))
            ->with(['role', 'permission', 'approvals'])
            ->latest('id');
    }

    /**
     * @return Builder<AccessRequest>
     */
    public function of(Model $requester): Builder
    {
        $holder = Holder::of($requester);

        return AccessRequest::query()
            ->where(static fn (Builder $query): Builder => $query->where('requester_type', $holder->type)->where('requester_id', $holder->id))
            ->orWhere(static fn (Builder $query): Builder => $query->where('subject_type', $holder->type)->where('subject_id', $holder->id))
            ->with(['role', 'permission', 'approvals'])
            ->latest('id');
    }

    private function reviewable(Model $reviewer, AccessRequest $request): Model
    {
        if (! $request->isPending()) {
            throw VaultValidationException::make('request_closed');
        }

        $holder = Holder::of($reviewer);

        if ($holder->is(new Holder($request->requester_type, $request->requester_id)) || $holder->is(new Holder($request->subject_type, $request->subject_id))) {
            throw VaultAuthorizationException::because('self_approval');
        }

        $this->authority->authorize($reviewer, VaultPermissions::RequestsReview, $request->tenant);
        $subject = $request->subject;

        return $subject instanceof Model ? $subject : throw VaultValidationException::make('unknown_holder');
    }

    private function vote(AccessRequest $request, Holder $reviewer, string $decision, ?string $reason): void
    {
        try {
            // A savepoint, so a duplicate vote leaves the outer transaction usable (PostgreSQL).
            $request->getConnection()->transaction(static function () use ($request, $reviewer, $decision, $reason): void {
                (new Approval)->forceFill([
                    'request_id' => $request->id,
                    'approver_type' => $reviewer->type,
                    'approver_id' => $reviewer->id,
                    'decision' => $decision,
                    'reason' => $reason === null ? null : mb_substr(trim($reason), 0, 500),
                    'created_at' => Date::now(),
                ])->save();
            });
        } catch (QueryException) {
            throw VaultValidationException::make('already_reviewed');
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array{0: Role|null, 1: Permission|null}
     */
    private function item(array $data, string $tenant): array
    {
        $role = is_string($data['role'] ?? null) && $data['role'] !== '' ? $data['role'] : null;
        $permission = is_string($data['permission'] ?? null) && $data['permission'] !== '' ? $data['permission'] : null;

        if (($role === null) === ($permission === null)) {
            throw VaultValidationException::make('choose_one_item', field: 'role');
        }

        if ($role !== null) {
            $found = $this->roles->require($role, $tenant);

            if ($found->isArchived() || ! $found->availableIn($tenant) || ($found->is_super && $tenant !== Scope::Global)) {
                throw VaultValidationException::make('unknown_role', ['role' => $role], 'role');
            }

            return [$found, null];
        }

        $found = $this->permissions->require((string) $permission);

        if (! $found->appliesIn($tenant)) {
            throw VaultValidationException::make('permission_wrong_scope', ['permission' => $found->name], 'permission');
        }

        return [null, $found];
    }

    private function holds(Model $subject, ?Role $role, ?Permission $permission, string $tenant): bool
    {
        $holder = Holder::of($subject);

        return $role !== null
            ? Assignment::query()->heldBy($holder->type, $holder->id)->where('role_id', $role->id)->where('tenant', $tenant)->active()->exists()
            : Grant::query()->heldBy($holder->type, $holder->id)->where('permission_id', $permission?->id)->where('tenant', $tenant)->active()->exists();
    }
}
