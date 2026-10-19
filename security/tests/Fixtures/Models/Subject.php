<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_published
 * @property int $institution_id
 */
final class Subject extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['is_published' => 'boolean'];
}
