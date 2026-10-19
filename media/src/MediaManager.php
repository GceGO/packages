<?php

declare(strict_types=1);

namespace Gcego\Media;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Attachments\MediaAttachments;
use Gcego\Media\Attachments\MediaReplacer;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\MediaVisibility;
use Gcego\Media\Diagnostics\MediaInspector;
use Gcego\Media\Diagnostics\MediaReport;
use Gcego\Media\Downloads\DownloadManager;
use Gcego\Media\Downloads\SignedMediaUrl;
use Gcego\Media\Exceptions\MediaNotFoundException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Uploads\UploadContext;
use Gcego\Media\Uploads\UploadManager;
use Gcego\Media\Uploads\UploadResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point for media:
 *
 *     $media->attach(model: $lesson, file: $uploadedFile, collection: 'lesson-resources');
 *     $media->upload($uploadedFile, 'library');                     // unattached
 *     $media->media($lesson, 'lesson-resources');                   // attached, in order
 *     $media->url($record, variant: 'thumb');                       // authorized route URL
 *     $media->signedUrl($record, minutes: 15);                       // expiring, signed
 *     return $media->download(media: $record, user: auth()->user());
 *     $media->replace($record, $newFile);  $media->delete($record);
 *
 * Every operation is authorized (MediaAccess) and tenant-scoped. Services
 * are resolved per call (safe in long-running workers).
 */
final readonly class MediaManager
{
    public function __construct(private Container $container) {}

    public function collection(MediaCollection $collection): void
    {
        $this->container->make(MediaCollectionRegistry::class)->register($collection);
    }

    /**
     * Store a file (unattached, e.g. into a library collection).
     *
     * @throws UploadException
     */
    public function upload(UploadedFile|string $file, ?string $collection = null, ?string $alt = null): MediaRecord
    {
        return $this->uploads()->upload($this->file($file), new UploadContext($collection ?? $this->config()->defaultCollection, alt: $alt));
    }

    /**
     * @param  iterable<UploadedFile>  $files
     * @return list<UploadResult>
     */
    public function uploadMany(iterable $files, ?string $collection = null, ?Model $model = null): array
    {
        return $this->uploads()->uploadMany($files, new UploadContext($collection ?? $this->config()->defaultCollection, $model));
    }

    /**
     * Attach a new file (stored first) or existing media to a model.
     *
     * @throws UploadException
     */
    public function attach(Model $model, UploadedFile|string|MediaRecord $file, ?string $collection = null, ?string $alt = null): MediaRecord
    {
        $collection ??= $this->config()->defaultCollection;

        if ($file instanceof MediaRecord) {
            $this->container->make(MediaAttachments::class)->attach($model, $file, $collection);

            return $file;
        }

        return $this->uploads()->upload($this->file($file), new UploadContext($collection, $model, alt: $alt));
    }

    public function detach(Model $model, MediaRecord $media, ?string $collection = null): bool
    {
        return $this->container->make(MediaAttachments::class)->detach($model, $media, $collection ?? $this->config()->defaultCollection);
    }

    /**
     * @return Collection<int, MediaRecord>
     */
    public function media(Model $model, ?string $collection = null): Collection
    {
        return $this->container->make(MediaAttachments::class)->media($model, $collection ?? $this->config()->defaultCollection);
    }

    /**
     * @throws MediaNotFoundException
     */
    public function find(string $ulid): MediaRecord
    {
        return $this->access()->find($ulid);
    }

    public function allows(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): bool
    {
        return $this->access()->allows($ability, $media, $owner);
    }

    /**
     * The URL to show or download media: the disk's public URL for public
     * media on a public disk, else the authorized media route.
     */
    public function url(MediaRecord $media, ?string $variant = null, bool $download = false): string
    {
        if ($media->visibility === MediaVisibility::Public && ! $download && $this->access()->available($media)) {
            $path = $variant === null ? $media->path : ($media->variant($variant)['path'] ?? null);
            $public = $path === null ? null : $this->container->make(MediaStorage::class)->publicUrl($media->disk, $path);

            if ($public !== null && $this->container->make(Repository::class)->get('filesystems.disks.'.$media->disk.'.visibility') === 'public') {
                return $public;
            }
        }

        return $this->urls()->route($media, $variant, $download);
    }

    public function signedUrl(MediaRecord $media, ?string $variant = null, bool $download = false, ?Model $for = null, ?int $minutes = null): string
    {
        $this->access()->authorize($download ? MediaAbility::Download : MediaAbility::View, $media);

        return $this->urls()->signed($media, $variant, $download, $for, $minutes);
    }

    public function download(MediaRecord $media, ?Authenticatable $user = null, bool $inline = false): Response
    {
        return $this->container->make(DownloadManager::class)->download($media, $user, $inline);
    }

    /**
     * @throws UploadException
     */
    public function replace(MediaRecord $media, UploadedFile|string $file, ?Model $owner = null): MediaRecord
    {
        return $this->container->make(MediaReplacer::class)->replace($media, $this->file($file), $owner);
    }

    public function delete(MediaRecord $media, bool $permanent = false, ?Model $owner = null): bool
    {
        return $this->container->make(MediaReplacer::class)->delete($media, $permanent, $owner);
    }

    /**
     * Queue processing again (variants are regenerated when $force).
     */
    public function regenerate(MediaRecord $media, bool $force = true): void
    {
        $this->access()->authorize(MediaAbility::Replace, $media);
        $this->uploads()->dispatch($media, $force);
    }

    public function inspect(): MediaReport
    {
        return $this->container->make(MediaInspector::class)->inspect();
    }

    /**
     * A local path is a trusted server file (not a browser upload).
     */
    private function file(UploadedFile|string $file): UploadedFile
    {
        return is_string($file) ? new UploadedFile($file, basename($file), null, null, true) : $file;
    }

    private function uploads(): UploadManager
    {
        return $this->container->make(UploadManager::class);
    }

    private function access(): MediaAccess
    {
        return $this->container->make(MediaAccess::class);
    }

    private function urls(): SignedMediaUrl
    {
        return $this->container->make(SignedMediaUrl::class);
    }

    private function config(): MediaConfig
    {
        return $this->container->make(MediaConfig::class);
    }
}
