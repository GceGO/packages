<?php

declare(strict_types=1);

namespace Gcego\Notifications\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $subject_id
 * @property string $name
 */
final class Topic extends Model
{
    protected $fillable = ['name'];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
