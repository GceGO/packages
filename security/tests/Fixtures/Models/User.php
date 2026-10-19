<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email_verified_at
 */
final class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles;
    use \Illuminate\Auth\MustVerifyEmail;

    protected $guarded = [];
}
