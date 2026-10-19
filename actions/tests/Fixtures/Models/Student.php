<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 */
final class Student extends Model
{
    use HasUuids;

    protected $fillable = ['name'];
}
