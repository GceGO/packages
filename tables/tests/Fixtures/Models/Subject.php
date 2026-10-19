<?php

declare(strict_types=1);

namespace Gcego\Tables\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property int $institution_id
 */
final class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'status', 'credits', 'fee', 'is_published', 'starts_on', 'published_at', 'completion', 'website', 'category_id', 'secret_notes', 'salary_band', 'api_token'];

    protected $hidden = ['api_token'];

    protected $casts = ['is_published' => 'boolean', 'credits' => 'float', 'starts_on' => 'date', 'published_at' => 'datetime'];

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
}
