<?php

declare(strict_types=1);

namespace Gcego\Media\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $locale
 * @property string|null $timezone
 */
final class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = [];
}
