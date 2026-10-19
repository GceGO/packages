<?php

declare(strict_types=1);

namespace Gcego\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * For models that have media:
 *
 *     final class Lesson extends Model { use HasMedia; }
 *
 *     $lesson->mediaIn('lesson-resources');      // attached media, in order
 *     $lesson->firstMedia('cover_image');
 *
 * The "gcegoMedia" relation is what tables and infolists eager-load. Reads
 * go through the relation; attaching and detaching go through
 * MediaManager (authorized).
 */
trait HasMedia
{
    /**
     * @return MorphToMany<MediaRecord, $this>
     */
    public function gcegoMedia(): MorphToMany
    {
        return $this->morphToMany(MediaRecord::class, 'attachable', 'gcego_media_attachments', 'attachable_id', 'media_id')
            ->withPivot(['collection', 'position'])
            ->withTimestamps()
            ->orderBy('gcego_media_attachments.position');
    }

    /**
     * @return Collection<int, MediaRecord>
     */
    public function mediaIn(string $collection): Collection
    {
        $loaded = $this->relationLoaded('gcegoMedia') ? $this->getRelation('gcegoMedia') : $this->gcegoMedia()->get();
        $media = [];

        foreach (is_iterable($loaded) ? $loaded : [] as $record) {
            $pivot = $record instanceof MediaRecord ? $record->getAttribute('pivot') : null;

            if ($record instanceof MediaRecord && $pivot instanceof Model && $pivot->getAttribute('collection') === $collection) {
                $media[] = $record;
            }
        }

        return new Collection($media);
    }

    public function firstMedia(string $collection): ?MediaRecord
    {
        return $this->mediaIn($collection)->first();
    }
}
