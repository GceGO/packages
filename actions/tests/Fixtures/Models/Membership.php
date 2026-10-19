<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $institution_id
 * @property string $status
 */
final class Membership extends Model
{
    protected $guarded = [];
}
