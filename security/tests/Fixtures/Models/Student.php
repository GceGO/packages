<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $national_id
 * @property int $institution_id
 */
final class Student extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['national_id'];
}
