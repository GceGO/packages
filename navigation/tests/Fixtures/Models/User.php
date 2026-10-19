<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 */
final class User extends Authenticatable
{
    protected $guarded = [];
}
