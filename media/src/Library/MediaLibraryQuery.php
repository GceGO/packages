<?php

declare(strict_types=1);

namespace Gcego\Media\Library;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Models\MediaRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The media a viewer browses: their tenant's media, all of it for those
 * who may manage media ("gcego-media.manage"), otherwise their own
 * uploads. Other people's files reach a viewer only through the records
 * they are attached to. Paginated, indexed, filtered on the server.
 */
final readonly class MediaLibraryQuery
{
    public function __construct(private MediaAccess $access) {}

    /**
     * @return Builder<MediaRecord>
     */
    public function query(MediaQuery $filters): Builder
    {
        $query = $this->access->query();
        $user = $this->access->user();

        if ($filters->mine || ! $this->access->allows(MediaAbility::Manage)) {
            $query->where('uploader_type', $user instanceof Model ? $user->getMorphClass() : '-')->where('uploader_id', $user instanceof Model ? $user->getKey() : 0);
        }

        if ($filters->search !== null) {
            $query->where('original_name', 'like', '%'.addcslashes($filters->search, '%_\\').'%');
        }

        if ($filters->collection !== null) {
            $query->where('collection', $filters->collection);
        }

        match ($filters->kind) {
            'image' => $query->whereIn('mime', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']),
            'video' => $query->where('mime', 'like', 'video/%'),
            'audio' => $query->where('mime', 'like', 'audio/%'),
            'pdf' => $query->where('mime', 'application/pdf'),
            'file' => $query->whereNotIn('mime', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'])->where('mime', 'not like', 'video/%')->where('mime', 'not like', 'audio/%'),
            default => null,
        };

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->from !== null) {
            $query->where('created_at', '>=', $filters->from);
        }

        if ($filters->until !== null) {
            $query->where('created_at', '<=', $filters->until);
        }

        if ($filters->minSize !== null) {
            $query->where('size', '>=', $filters->minSize);
        }

        if ($filters->maxSize !== null) {
            $query->where('size', '<=', $filters->maxSize);
        }

        return $query->orderBy($filters->sort, $filters->direction)->orderBy('id', $filters->direction);
    }

    /**
     * @return LengthAwarePaginator<int, MediaRecord>
     */
    public function page(MediaQuery $filters, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($filters)->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
    }
}
