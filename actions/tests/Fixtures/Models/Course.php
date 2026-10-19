<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $title
 */
final class Course extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['title'];
}
