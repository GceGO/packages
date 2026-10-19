<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class Subject extends Model
{
    protected $guarded = [];
}
