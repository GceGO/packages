<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $institution_id
 * @property string $role
 * @property string $status
 */
final class Membership extends Model
{
    protected $guarded = [];
}
