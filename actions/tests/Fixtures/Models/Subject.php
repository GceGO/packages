<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_published
 * @property int $level
 * @property int $institution_id
 */
final class Subject extends Model
{
    use SoftDeletes;

    /** Only these attributes are mass assignable; institution_id never is. */
    protected $fillable = ['name', 'code', 'is_published', 'level'];

    protected $casts = ['is_published' => 'boolean', 'level' => 'integer'];
}
