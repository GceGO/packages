<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class Tag extends Model
{
    protected $fillable = ['name'];
}
