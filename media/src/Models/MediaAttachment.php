<?php

declare(strict_types=1);

namespace Gcego\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Media attached to a model, in a collection, at a position. One media
 * record may be attached to several models (shared media).
 *
 * @property int $id
 * @property int $media_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string $collection
 * @property int $position
 */
class MediaAttachment extends Model
{
    protected $table = 'gcego_media_attachments';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<MediaRecord, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaRecord::class, 'media_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
