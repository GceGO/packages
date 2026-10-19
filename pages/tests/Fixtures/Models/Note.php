<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A ULID-keyed record (RecordKey "ulid").
 */
final class Note extends Model
{
    use HasUlids;

    protected $guarded = [];
}
