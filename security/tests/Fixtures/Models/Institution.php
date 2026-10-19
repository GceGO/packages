<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $active
 */
final class Institution extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}
