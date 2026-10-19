<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-owned subject (institution_id). internal_notes is sensitive and hidden.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 */
final class Subject extends Model
{
    protected $fillable = ['name', 'code', 'status'];

    protected $hidden = ['internal_notes'];
}
