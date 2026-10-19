<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A custom string primary key.
 */
final class Course extends Model
{
    protected $primaryKey = 'course_code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
