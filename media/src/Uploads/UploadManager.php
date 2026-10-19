<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Attachments\MediaAttachments;
use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\Jobs\ProcessMedia;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Storage\StoragePathResolver;
use Gcego\Media\Support\Keys;
use Gcego\Media\Transformations\ImageTransformer;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Stores uploads safely:
 *
 *  1. authorize (upload; attach when a model is given);
 *  2. Livewire temporary files: only their uploader, unexpired;
 *  3. validate the content (UploadValidator) and the collection's file count;
 *  4. strip image metadata (gcego-media.images.strip_metadata);
 *  5. store under a generated path with a random name (MediaStorage);
 *  6. create the record (and the attachment) in a transaction, deleting
 *     the stored file again when that fails (no orphan, no false success);
 *  7. queue processing (scan, metadata, variants) after the commit.
 */
final readonly class UploadManager
{
    public function __construct(
        private MediaConfig $config,
        private MediaAccess $access,
        private MediaCollectionRegistry $collections,
        private UploadValidator $validator,
        private MediaStorage $storage,
        private StoragePathResolver $paths,
        private TemporaryUploads $temporary,
        private MediaAttachments $attachments,
        private ImageTransformer $images,
        private ConnectionResolverInterface $connections,
    ) {}

    public function rules(UploadContext $context): CollectionRules
    {
        return CollectionRules::resolve($this->config, $this->collections->get($context->collection), $context->rules);
    }

    /**
     * Store one file.
     *
     * @throws UploadException
     */
    public function upload(UploadedFile $file, UploadContext $context): MediaRecord
    {
        $this->authorize($context);
        $rules = $this->rules($context);

        if ($context->owner !== null && ! $rules->single && $this->attachments->count($context->owner, $rules->collection) >= $rules->maxFiles) {
            throw UploadException::refused('too_many', ['max' => $rules->maxFiles]);
        }

        return $this->store($file, $context, $rules);
    }

    /**
     * Store several files, each validated on its own (partial success).
     *
     * @param  iterable<UploadedFile>  $files
     * @return list<UploadResult>
     */
    public function uploadMany(iterable $files, UploadContext $context): array
    {
        $this->authorize($context);
        $rules = $this->rules($context);
        $room = $rules->maxFiles - ($context->owner === null ? 0 : $this->attachments->count($context->owner, $rules->collection));
        $results = [];

        foreach ($files as $file) {
            $name = FileInspector::displayName($file->getClientOriginalName());

            if ($room <= 0 && ! $rules->single) {
                $results[] = new UploadResult($name, reason: 'too_many', errors: [(string) __('gcego-media::messages.errors.too_many', ['max' => $rules->maxFiles])]);

                continue;
            }

            try {
                $results[] = new UploadResult($name, $this->store($file, $context, $rules));
                $room--;
            } catch (UploadException $exception) {
                $results[] = new UploadResult($name, reason: $exception->reason, errors: $exception->errors);
            }
        }

        return $results;
    }

    /**
     * Validate a file without storing it (form validation).
     *
     * @throws UploadException
     */
    public function check(UploadedFile $file, CollectionRules $rules): InspectedFile
    {
        if ($file instanceof TemporaryUploadedFile && ! $this->temporary->owns($file)) {
            throw UploadException::refused('temporary');
        }

        if (! $file->isValid()) {
            throw UploadException::refused('failed');
        }

        return $this->validator->validate($file->getRealPath(), $file->getClientOriginalName(), $rules);
    }

    /**
     * @throws UploadException
     */
    public function store(UploadedFile $file, UploadContext $context, CollectionRules $rules): MediaRecord
    {
        $inspected = $this->check($file, $rules);
        $local = $this->prepare($inspected);
        $ulid = strtolower((string) Str::ulid());
        $path = $this->paths->original($this->paths->directory($ulid, $rules->collection, $this->access->tenant()), $inspected->extension);
        $user = $this->access->user();

        try {
            $this->storage->putFile($rules->disk, $path, $local['path'], $rules->visibility->value, $inspected->type);
        } finally {
            if ($local['temporary']) {
                @unlink($local['path']);
            }
        }

        $connection = $this->connections->connection();

        // Inside a surrounding transaction (a form saved through gcego/actions), a later rollback must not leave the file behind.
        if ($connection instanceof Connection && $connection->transactionLevel() > 0) {
            $connection->afterRollBack(fn () => $this->storage->delete($rules->disk, $path));
        }

        try {
            $media = $connection->transaction(function () use ($ulid, $rules, $path, $inspected, $local, $user, $context): MediaRecord {
                $media = new MediaRecord;
                $media->forceFill([
                    'ulid' => $ulid,
                    'tenant' => $this->access->tenant(),
                    'uploader_type' => $user instanceof Model ? $user->getMorphClass() : null,
                    'uploader_id' => $user instanceof Model ? $user->getKey() : null,
                    'collection' => $rules->collection,
                    'disk' => $rules->disk,
                    'path' => $path,
                    'original_name' => $inspected->name,
                    'mime' => $inspected->type,
                    'extension' => $inspected->extension,
                    'size' => $local['size'],
                    'checksum' => $local['checksum'],
                    'visibility' => $rules->visibility,
                    'status' => MediaStatus::Pending,
                    'scan_status' => $this->config->scanner === null ? ScanStatus::Unscanned : ScanStatus::Pending,
                    'metadata' => $local['image'] === null ? null : ['width' => $local['image']['width'], 'height' => $local['image']['height']],
                    'alt' => $context->alt === null ? null : mb_substr($context->alt, 0, 255),
                ])->save();

                if ($context->owner !== null) {
                    $this->attachments->attachTrusted($context->owner, $media, $rules);
                }

                return $media;
            });
        } catch (Throwable $exception) {
            // Compensate: the file must not outlive a record that was never created.
            $this->storage->delete($rules->disk, $path);
            report($exception);

            throw UploadException::refused('storage');
        }

        if ($file instanceof TemporaryUploadedFile) {
            $this->temporary->consume($file);
        }

        $this->dispatch($media);

        return $media;
    }

    public function dispatch(MediaRecord $media, bool $force = false): void
    {
        if (! $this->config->transformations && $this->config->scanner === null) {
            $media->forceFill(['status' => MediaStatus::Ready, 'processed_at' => now()])->save();

            return;
        }

        $job = new ProcessMedia(Keys::int($media->getKey()), $force);
        $job->tries = $this->config->tries;
        $job->onQueue($this->config->queue)->onConnection($this->config->queueConnection);
        dispatch($job);
    }

    /**
     * @throws UploadException
     */
    private function authorize(UploadContext $context): void
    {
        if (! $this->access->allows(MediaAbility::Upload) || ($context->owner !== null && ! $this->access->allows(MediaAbility::Attach, null, $context->owner))) {
            throw UploadException::refused('denied');
        }
    }

    /**
     * The local file to store: the upload itself, or a metadata-free re-encoding of it.
     * When "temporary" is true the caller deletes "path" after storing it.
     *
     * @return array{path: string, temporary: bool, size: int, checksum: string, image: array{width: int, height: int}|null}
     */
    public function prepare(InspectedFile $file): array
    {
        $image = $file->image === null ? null : ['width' => $file->image['width'], 'height' => $file->image['height']];

        if ($this->config->stripMetadata && $file->image !== null && ImageTransformer::available()) {
            try {
                $stripped = $this->images->stripped($file->path, $file->type);
            } catch (Throwable) {
                $stripped = null;
            }

            $temporary = $stripped === null ? false : tempnam(sys_get_temp_dir(), 'gcego-media-');

            if ($stripped !== null && is_string($temporary) && file_put_contents($temporary, $stripped['contents']) !== false) {
                return ['path' => $temporary, 'temporary' => true, 'size' => strlen($stripped['contents']), 'checksum' => hash('sha256', $stripped['contents']), 'image' => ['width' => $stripped['width'], 'height' => $stripped['height']]];
            }
        }

        return ['path' => $file->path, 'temporary' => false, 'size' => $file->size, 'checksum' => (string) hash_file('sha256', $file->path), 'image' => $image];
    }
}
