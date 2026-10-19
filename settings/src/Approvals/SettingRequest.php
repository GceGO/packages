<?php

declare(strict_types=1);

namespace Gcego\Settings\Approvals;

use Gcego\Settings\Support\Keys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A change waiting for its approval workflow: the subject of the
 * gcego/workflows instance. The requested value is always encrypted (it
 * may be a secret, and approvers never need to read it in plaintext to
 * decide on the change's shape); it is erased once the request is
 * applied or refused.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property string $scope
 * @property string $scope_key
 * @property string|null $tenant
 * @property string|null $value
 * @property string $type
 * @property int $base_version
 * @property string $workflow
 * @property string $status
 * @property string|null $requested_by_type
 * @property string|null $requested_by_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SettingRequest extends Model
{
    public const string Pending = 'pending';

    public const string Applied = 'applied';

    public const string Refused = 'refused';

    /** The setting changed after the request was made: not applied. */
    public const string Conflicted = 'conflicted';

    /** The approved value no longer passes validation or authorization rules: not applied. */
    public const string Invalid = 'invalid';

    protected $table = 'gcego_setting_requests';

    protected $guarded = ['*'];

    protected $hidden = ['value', 'scope_key', 'requested_by_type', 'requested_by_id'];

    public function getConnectionName(): ?string
    {
        $connection = config('gcego-settings.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function requester(): MorphTo
    {
        return $this->morphTo('requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::Pending;
    }

    public function requestedBy(?Model $user): bool
    {
        return $user !== null && $this->requested_by_type === $user->getMorphClass() && $this->requested_by_id === Keys::string($user->getKey());
    }

    protected function casts(): array
    {
        return ['base_version' => 'integer', 'decided_at' => 'datetime'];
    }
}
