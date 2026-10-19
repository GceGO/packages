<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $label
 */
final class Tag extends Model
{
    protected $guarded = [];
}
