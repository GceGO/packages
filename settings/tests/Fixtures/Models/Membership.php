<?php

declare(strict_types=1);

namespace Gcego\Settings\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $institution_id
 * @property list<string>|null $permissions
 */
final class Membership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
