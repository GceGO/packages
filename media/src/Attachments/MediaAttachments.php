<?php

declare(strict_types=1);

namespace Gcego\Media\Attachments;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaAttachment;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Keys;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Media attached to models: ordered per collection, shared between models
 * when attached more than once, single-file collections replacing their
 * file. Attaching needs "attach" (the owner's "update"); detaching never
 * deletes media another model still uses.
 */
final readonly class MediaAttachments
{
    public function __construct(
        private MediaAccess $access,
        private MediaCollectionRegistry $collections,
        private MediaConfig $config,
        private ConnectionResolverInterface $connections,
    ) {}

    /**
     * @throws MediaAccessDeniedException|UploadException
     */
    public function attach(Model $owner, MediaRecord $media, string $collection): MediaAttachment
    {
        $this->access->authorize(MediaAbility::Attach, $media, $owner);
        $rules = CollectionRules::resolve($this->config, $this->collections->get($collection));

        if (! $rules->accepts($media->mime)) {
            throw UploadException::refused('type', ['types' => implode(', ', $rules->types)]);
        }

        return $this->connections->connection()->transaction(fn (): MediaAttachment => $this->attachTrusted($owner, $media, $rules));
    }

    /**
     * Attach without re-authorizing (the caller authorized the upload into this owner).
     *
     * @throws UploadException
     */
    public function attachTrusted(Model $owner, MediaRecord $media, CollectionRules $rules): MediaAttachment
    {
        $existing = $this->query($owner, $rules->collection)->where('media_id', $media->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($rules->single) {
            foreach ($this->query($owner, $rules->collection)->get() as $previous) {
                $previous->delete();
            }
        } elseif ($this->count($owner, $rules->collection) >= $rules->maxFiles) {
            throw UploadException::refused('too_many', ['max' => $rules->maxFiles]);
        }

        $attachment = new MediaAttachment;
        $attachment->forceFill([
            'media_id' => $media->getKey(),
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => Keys::string($owner->getKey()),
            'collection' => $rules->collection,
            'position' => Keys::int($this->query($owner, $rules->collection)->max('position')) + 1,
        ])->save();

        return $attachment;
    }

    /**
     * Detach media from a model; the media itself is kept (deleting it is separate).
     *
     * @throws MediaAccessDeniedException
     */
    public function detach(Model $owner, MediaRecord $media, string $collection): bool
    {
        $this->access->authorize(MediaAbility::Attach, null, $owner);

        return $this->query($owner, $collection)->where('media_id', $media->getKey())->delete() > 0;
    }

    /**
     * Make the collection exactly these media, in this order (ids already authorized by the caller).
     *
     * @param  list<int>  $mediaIds
     */
    public function syncTrusted(Model $owner, string $collection, array $mediaIds): void
    {
        $this->query($owner, $collection)->whereNotIn('media_id', $mediaIds === [] ? [0] : $mediaIds)->delete();

        foreach (array_values($mediaIds) as $position => $id) {
            $this->query($owner, $collection)->where('media_id', $id)->update(['position' => $position + 1]);
        }
    }

    /**
     * @param  list<string>  $ulids  The new order (unknown ids are ignored).
     *
     * @throws MediaAccessDeniedException
     */
    public function reorder(Model $owner, string $collection, array $ulids): void
    {
        $this->access->authorize(MediaAbility::Attach, null, $owner);
        $ids = $this->media($owner, $collection)->pluck('id', 'ulid');
        $ordered = [];

        foreach ($ulids as $ulid) {
            if (is_string($ulid) && isset($ids[strtolower($ulid)])) {
                $ordered[] = Keys::int($ids[strtolower($ulid)]);
            }
        }

        foreach ($ids as $id) {
            if (! in_array(Keys::int($id), $ordered, true)) {
                $ordered[] = Keys::int($id);
            }
        }

        foreach ($ordered as $position => $id) {
            $this->query($owner, $collection)->where('media_id', $id)->update(['position' => $position + 1]);
        }
    }

    public function count(Model $owner, string $collection): int
    {
        return $this->query($owner, $collection)->count();
    }

    /**
     * The owner's media in a collection, in order (tenant-scoped).
     *
     * @return Collection<int, MediaRecord>
     */
    public function media(Model $owner, string $collection): Collection
    {
        return $this->access->query()
            ->join('gcego_media_attachments', 'gcego_media_attachments.media_id', '=', 'gcego_media.id')
            ->where('gcego_media_attachments.attachable_type', $owner->getMorphClass())
            ->where('gcego_media_attachments.attachable_id', Keys::string($owner->getKey()))
            ->where('gcego_media_attachments.collection', $collection)
            ->orderBy('gcego_media_attachments.position')
            ->select('gcego_media.*')
            ->get();
    }

    /**
     * @return Builder<MediaAttachment>
     */
    private function query(Model $owner, string $collection): Builder
    {
        return MediaAttachment::query()->where('attachable_type', $owner->getMorphClass())->where('attachable_id', Keys::string($owner->getKey()))->where('collection', $collection);
    }
}
