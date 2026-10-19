<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $institution_id
 * @property string $role
 */
final class Membership extends Model
{
    protected $guarded = [];
}
