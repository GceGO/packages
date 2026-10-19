<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Report extends Model
{
    use HasUlids;

    protected $guarded = [];
}
