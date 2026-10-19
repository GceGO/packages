<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Student extends Model
{
    protected $guarded = [];

    protected $hidden = ['national_id'];

    protected $casts = ['enrolled_at' => 'datetime'];
}
