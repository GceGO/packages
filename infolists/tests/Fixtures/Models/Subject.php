<?php

declare(strict_types=1);

namespace Gcego\Infolists\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property int $institution_id
 */
final class Subject extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['api_token'];

    protected $casts = ['is_published' => 'boolean', 'objectives' => 'array', 'settings' => 'array', 'languages' => 'array', 'starts_on' => 'date', 'published_at' => 'datetime'];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Topic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
