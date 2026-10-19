<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A UUID-keyed record (RecordKey "uuid").
 */
final class Document extends Model
{
    use HasUuids;

    protected $guarded = [];
}
