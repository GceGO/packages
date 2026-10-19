<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $institution_id
 */
final class Subject extends Model
{
    protected $fillable = ['name', 'code'];
}
