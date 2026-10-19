<?php

declare(strict_types=1);

namespace Gcego\Media\Models;

use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Support\Keys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One stored file. Its public identity is the ULID (never the numeric id
 * or the storage path). Nothing is mass assignable: only gcego/media
 * services write records.
 *
 * @property int $id
 * @property string $ulid
 * @property string|null $tenant
 * @property string $collection
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property string $extension
 * @property int $size
 * @property string $checksum
 * @property MediaVisibility $visibility
 * @property MediaStatus $status
 * @property ScanStatus $scan_status
 * @property array<string, mixed>|null $metadata
 * @property array<string, array{path: string, width: int, height: int, mime: string, size: int, signature: string}>|null $variants
 * @property string|null $alt
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 */
class MediaRecord extends Model
{
    use SoftDeletes;

    protected $table = 'gcego_media';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'path', 'disk', 'tenant', 'uploader_type', 'uploader_id', 'checksum', 'variants'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<MediaAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class, 'media_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInTenant(Builder $query, ?string $tenant): Builder
    {
        return $tenant === null ? $query->whereNull('tenant') : $query->where('tenant', $tenant);
    }

    public function isImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public function kind(): string
    {
        return match (true) {
            $this->isImage() => 'image',
            str_starts_with($this->mime, 'video/') => 'video',
            str_starts_with($this->mime, 'audio/') => 'audio',
            $this->mime === 'application/pdf' => 'pdf',
            default => 'file',
        };
    }

    public function meta(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * @return array{path: string, width: int, height: int, mime: string, size: int, signature: string}|null
     */
    public function variant(string $name): ?array
    {
        return $this->variants[$name] ?? null;
    }

    public function isUploadedBy(?Model $user): bool
    {
        return $user !== null && $this->getAttribute('uploader_type') === $user->getMorphClass() && Keys::string($this->getAttribute('uploader_id')) === Keys::string($user->getKey());
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visibility' => MediaVisibility::class,
            'status' => MediaStatus::class,
            'scan_status' => ScanStatus::class,
            'metadata' => 'array',
            'variants' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
