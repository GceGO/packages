<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Enums\RequestStatus;
use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A request for a role or a permission, for the requester or (asked by an
 * administrator) for someone else. Approving never happens by itself: it
 * needs approvals_required distinct approvers, none of them the requester or
 * the beneficiary. Written only through AccessRequests.
 *
 * @property int $id
 * @property string $requester_type
 * @property string $requester_id
 * @property string $subject_type
 * @property string $subject_id
 * @property int|null $role_id
 * @property int|null $permission_id
 * @property string $tenant
 * @property string $reason
 * @property Carbon|null $requested_until
 * @property RequestStatus $status
 * @property int $approvals_required
 * @property bool $is_sensitive
 * @property Carbon|null $approved_until
 * @property string|null $decided_by_type
 * @property string|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property int|null $assignment_id
 * @property int|null $grant_id
 * @property string|null $pending_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccessRequest extends Model
{
    use UsesVaultConnection;

    protected $table = 'gcego_vault_requests';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'request_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function requester(): MorphTo
    {
        return $this->morphTo('requester', 'requester_type', 'requester_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    /**
     * "role:teacher" or "permission:subjects.update".
     */
    public function item(): string
    {
        return $this->role_id !== null ? 'role:'.($this->role->name ?? $this->role_id) : 'permission:'.($this->permission->name ?? $this->permission_id);
    }

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class, 'requested_until' => 'datetime', 'approved_until' => 'datetime', 'decided_at' => 'datetime',
            'approvals_required' => 'integer', 'is_sensitive' => 'boolean', 'role_id' => 'integer', 'permission_id' => 'integer',
        ];
    }
}
