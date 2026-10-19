<?php

declare(strict_types=1);

namespace Gcego\Media\Attachments;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Storage\StoragePathResolver;
use Gcego\Media\Uploads\UploadManager;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Replacing and deleting media safely:
 *
 * - replace(): the new file is validated and stored first, the record
 *   updated in a transaction, and only after the commit are the old file
 *   and its variants deleted. A failure leaves the original untouched.
 * - delete(): soft delete (the record disappears, files stay) or permanent
 *   (files and attachments removed). Shared media is only deleted when
 *   asked for explicitly; detaching never deletes.
 */
final readonly class MediaReplacer
{
    public function __construct(
        private MediaAccess $access,
        private UploadManager $uploads,
        private MediaStorage $storage,
        private StoragePathResolver $paths,
        private MediaCollectionRegistry $collections,
        private MediaConfig $config,
        private ConnectionResolverInterface $connections,
    ) {}

    /**
     * @throws UploadException
     */
    public function replace(MediaRecord $media, UploadedFile $file, ?Model $owner = null): MediaRecord
    {
        $this->access->authorize(MediaAbility::Replace, $media, $owner);
        $rules = CollectionRules::resolve($this->config, $this->collections->get($media->collection));
        $inspected = $this->uploads->check($file, $rules);
        $path = $this->paths->original(dirname($media->path), $inspected->extension);
        $old = [$media->path, ...array_values(array_map(static fn (array $variant): string => $variant['path'], $media->variants ?? []))];

        $local = $this->uploads->prepare($inspected);

        try {
            $this->storage->putFile($media->disk, $path, $local['path'], $media->visibility->value, $inspected->type);
        } finally {
            if ($local['temporary']) {
                @unlink($local['path']);
            }
        }

        $connection = $this->connections->connection();

        try {
            $connection->transaction(static function () use ($media, $path, $inspected, $local): void {
                $media->forceFill([
                    'path' => $path,
                    'original_name' => $inspected->name,
                    'mime' => $inspected->type,
                    'extension' => $inspected->extension,
                    'size' => $local['size'],
                    'checksum' => $local['checksum'],
                    'metadata' => $local['image'],
                    'variants' => null,
                    'status' => MediaStatus::Pending,
                    'scan_status' => ScanStatus::Unscanned,
                    'processed_at' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            $this->storage->delete($media->disk, $path);
            report($exception);

            throw UploadException::refused('storage');
        }

        $media->forceFill(['scan_status' => $this->config->scanner === null ? ScanStatus::Unscanned : ScanStatus::Pending])->save();
        $delete = fn () => $this->storage->delete($media->disk, ...$old);
        $connection instanceof Connection && $connection->transactionLevel() > 0 ? $connection->afterCommit($delete) : $delete();
        $this->uploads->dispatch($media);

        return $media;
    }

    public function delete(MediaRecord $media, bool $permanent = false, ?Model $owner = null): bool
    {
        $this->access->authorize(MediaAbility::Delete, $media, $owner);

        return $permanent ? $this->purge($media) : (bool) $media->delete();
    }

    /**
     * Remove the files and the record (attachments go with it).
     */
    public function purge(MediaRecord $media): bool
    {
        $files = [$media->path, ...array_values(array_map(static fn (array $variant): string => $variant['path'], $media->variants ?? []))];

        if (! $this->storage->delete($media->disk, ...$files)) {
            return false;
        }

        return (bool) $media->forceDelete();
    }
}
