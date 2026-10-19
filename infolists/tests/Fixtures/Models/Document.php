<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Document extends Model
{
    use HasUuids;

    protected $guarded = [];
}
