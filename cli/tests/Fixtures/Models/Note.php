<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without $fillable or a table (inferred fields, read-only resources).
 */
final class Note extends Model
{
    protected $guarded = ['*'];
}
