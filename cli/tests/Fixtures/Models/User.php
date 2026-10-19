<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 */
final class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = [];
}
