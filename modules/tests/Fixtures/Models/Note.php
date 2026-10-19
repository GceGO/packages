<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without a policy (diagnostics fixture).
 */
final class Note extends Model
{
    protected $guarded = [];
}
