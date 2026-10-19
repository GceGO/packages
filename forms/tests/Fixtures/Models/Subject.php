<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $secret_notes
 * @property int $institution_id
 */
final class Subject extends Model
{
    protected $fillable = ['name', 'code', 'email', 'website', 'kind', 'track', 'level', 'credits', 'is_published', 'agreed', 'starts_on', 'published_at', 'lesson_time', 'description', 'body', 'objectives', 'category_id', 'syllabus', 'password', 'secret_notes', 'internal_flag', 'origin'];

    protected $hidden = ['password'];

    protected $casts = ['is_published' => 'boolean', 'agreed' => 'boolean', 'level' => 'integer', 'credits' => 'float', 'starts_on' => 'date', 'published_at' => 'datetime', 'objectives' => 'array', 'track' => 'array'];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function notOptions(): HasMany
    {
        return $this->hasMany(Category::class, 'institution_id', 'institution_id');
    }
}
