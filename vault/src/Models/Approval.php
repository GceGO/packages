<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One reviewer's decision on an access request (unique per reviewer).
 *
 * @property int $id
 * @property int $request_id
 * @property string $approver_type
 * @property string $approver_id
 * @property string $decision
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
class Approval extends Model
{
    use UsesVaultConnection;

    public const string Approve = 'approve';

    public const string Reject = 'reject';

    public const null UPDATED_AT = null;

    protected $table = 'gcego_vault_approvals';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<AccessRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approver(): MorphTo
    {
        return $this->morphTo('approver', 'approver_type', 'approver_id');
    }
}
