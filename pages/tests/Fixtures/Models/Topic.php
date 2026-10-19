<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record keyed by its slug (RecordKey "slug").
 */
final class Topic extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'slug';

    protected $keyType = 'string';

    protected $guarded = [];
}
