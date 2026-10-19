<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActivityLog extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
