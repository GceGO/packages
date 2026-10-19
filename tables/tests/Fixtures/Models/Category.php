<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $institution_id
 * @property bool $active
 */
final class Category extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}
