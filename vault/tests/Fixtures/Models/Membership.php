<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The application's own membership records (who belongs to which institution).
 *
 * @property int $user_id
 * @property int $institution_id
 */
final class Membership extends Model
{
    protected $guarded = [];
}
