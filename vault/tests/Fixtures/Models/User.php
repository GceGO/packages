<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests\Fixtures\Models;

use Gcego\Vault\Concerns\HasVaultAccess;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 */
final class User extends Authenticatable
{
    use HasVaultAccess;

    protected $guarded = [];
}
