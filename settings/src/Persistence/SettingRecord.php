<?php

declare(strict_types=1);

namespace Gcego\Settings\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One stored override: a setting key at one scope instance. The value is
 * JSON text, or encrypted JSON text when encrypted is true; it is never
 * cast or decrypted by the model (SettingsReader does that, strictly).
 * Written only through SettingsWriter; never mass-assigned.
 *
 * @property int $id
 * @property string $key
 * @property string $scope
 * @property string $scope_key
 * @property string $value
 * @property string $type
 * @property bool $encrypted
 * @property int $version
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SettingRecord extends Model
{
    protected $table = 'gcego_settings';

    protected $guarded = ['*'];

    protected $hidden = ['value'];

    public function getConnectionName(): ?string
    {
        $connection = config('gcego-settings.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['encrypted' => 'boolean', 'version' => 'integer'];
    }
}
