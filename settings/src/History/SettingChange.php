<?php

declare(strict_types=1);

namespace Gcego\Settings\History;

use Gcego\Settings\Exceptions\InvalidSettingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An append-only history entry: once written it is never updated or
 * deleted through the model (application-level immutability). Values are
 * JSON text of non-sensitive settings only, cut to
 * gcego-settings.history.value_limit; sensitive changes are "redacted"
 * and keep no value at all, not even encrypted.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property string|null $group
 * @property string $scope
 * @property string $scope_key
 * @property string|null $tenant
 * @property string $change
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $actor_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property bool $redacted
 * @property int $version
 * @property string $correlation
 * @property Carbon|null $created_at
 */
class SettingChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gcego_setting_changes';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'scope_key', 'tenant', 'actor_type', 'actor_id'];

    public function getConnectionName(): ?string
    {
        $connection = config('gcego-settings.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    protected static function booted(): void
    {
        static::updating(static fn (): bool => throw InvalidSettingException::definition('Settings history', 'entries are append-only.'));
        static::deleting(static fn (): bool => throw InvalidSettingException::definition('Settings history', 'entries are append-only.'));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function changeType(): ChangeType
    {
        return ChangeType::tryFrom($this->change) ?? ChangeType::Updated;
    }

    protected function casts(): array
    {
        return ['redacted' => 'boolean', 'version' => 'integer', 'created_at' => 'datetime'];
    }
}
