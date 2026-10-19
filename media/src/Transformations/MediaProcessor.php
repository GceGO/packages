<?php

declare(strict_types=1);

namespace Gcego\Media\Transformations;

use Gcego\Media\Collections\CollectionRules;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Contracts\MalwareScanner;
use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\MediaProcessingException;
use Gcego\Media\MediaConfig;
use Gcego\Media\Metadata\MediaMetadataExtractor;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Storage\StoragePathResolver;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Processes one media record: malware scan (when configured), metadata,
 * then the collection's image variants. Idempotent: a record already
 * being processed is skipped, and variants whose definition did not change
 * are kept. Failures are recorded on the record (status "failed",
 * metadata.processing_error) and rethrown so the queue can retry; the
 * original file is never modified.
 */
final readonly class MediaProcessor
{
    public function __construct(
        private Container $container,
        private MediaConfig $config,
        private MediaStorage $storage,
        private StoragePathResolver $paths,
        private MediaMetadataExtractor $metadata,
        private ImageTransformer $images,
        private MediaCollectionRegistry $collections,
    ) {}

    /**
     * @param  bool  $force  Regenerate every variant.
     *
     * @throws MediaProcessingException
     */
    public function process(MediaRecord $media, bool $force = false): MediaRecord
    {
        $claimed = MediaRecord::query()->whereKey($media->getKey())->where('status', '!=', MediaStatus::Processing->value)
            ->update(['status' => MediaStatus::Processing->value, 'updated_at' => now()]);

        if ($claimed === 0) {
            return $media->refresh();
        }

        // The model must reflect the claim, or saving "ready" afterwards would look like no change.
        $media->forceFill(['status' => MediaStatus::Processing])->syncOriginalAttribute('status');

        $local = null;

        try {
            $local = $this->storage->localCopy($media->disk, $media->path) ?? throw MediaProcessingException::because('The stored file is missing.');

            if (! $this->scan($media, $local)) {
                return $media->refresh();
            }

            $metadata = [...($media->metadata ?? []), ...$this->metadata->extract($local, $media->mime)];
            unset($metadata['processing_error']);
            $variants = $this->variants($media, $local, $force);

            $media->forceFill(['metadata' => $metadata === [] ? null : $metadata, 'variants' => $variants === [] ? null : $variants, 'status' => MediaStatus::Ready, 'processed_at' => now()])->save();

            return $media;
        } catch (Throwable $exception) {
            $media->forceFill(['status' => MediaStatus::Failed, 'metadata' => [...($media->metadata ?? []), 'processing_error' => mb_substr(class_basename($exception).': '.$exception->getMessage(), 0, 250)]])->save();

            throw $exception instanceof MediaProcessingException ? $exception : MediaProcessingException::because($exception->getMessage());
        } finally {
            if (is_string($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * The scan decides whether the media may ever be served.
     */
    private function scan(MediaRecord $media, string $local): bool
    {
        if ($this->config->scanner === null) {
            return true;
        }

        $scanner = $this->container->make($this->config->scanner);

        try {
            $result = $scanner instanceof MalwareScanner ? $scanner->scan($local, $media->mime) : null;
        } catch (Throwable $exception) {
            report($exception);
            $result = null;
        }

        $status = $result !== null ? $result->status : ScanStatus::Error;
        $quarantine = $status === ScanStatus::Infected || ($status !== ScanStatus::Clean && $this->config->scanFailure === 'quarantine');
        $media->forceFill([
            'scan_status' => $status,
            'status' => $quarantine ? MediaStatus::Quarantined : MediaStatus::Processing,
            'metadata' => [...($media->metadata ?? []), ...($result?->signature === null ? [] : ['scan_signature' => $result->signature])],
        ])->save();

        return ! $quarantine;
    }

    /**
     * @return array<string, array{path: string, width: int, height: int, mime: string, size: int, signature: string}>
     */
    private function variants(MediaRecord $media, string $local, bool $force): array
    {
        $rules = CollectionRules::resolve($this->config, $this->collections->get($media->collection));
        $existing = $media->variants ?? [];

        if (! $media->isImage() || ! ImageTransformer::available() || ! $this->config->transformations) {
            return $existing;
        }

        $variants = [];

        foreach ($rules->variants as $variant) {
            $name = $variant->variantName();
            $current = $existing[$name] ?? null;

            if (! $force && $current !== null && $current['signature'] === $variant->signature() && $this->storage->exists($media->disk, $current['path'])) {
                $variants[$name] = $current;

                continue;
            }

            $variants[$name] = $this->generate($media, $local, $variant);
        }

        // Variants no longer defined are removed with their files.
        $stale = array_diff_key($existing, $variants);
        $this->storage->delete($media->disk, ...array_values(array_map(static fn (array $variant): string => $variant['path'], $stale)));

        return $variants;
    }

    /**
     * @return array{path: string, width: int, height: int, mime: string, size: int, signature: string}
     */
    private function generate(MediaRecord $media, string $local, Variant $variant): array
    {
        $image = $this->images->variant($local, $media->mime, $variant);
        $extension = $image['mime'] === 'image/jpeg' ? 'jpg' : substr($image['mime'], 6);
        $path = $this->paths->variant($media->path, $variant->variantName(), $extension);

        if (! $this->storage->putContents($media->disk, $path, $image['contents'], $media->visibility->value, $image['mime'])) {
            throw MediaProcessingException::because('The variant "'.$variant->variantName().'" could not be stored.');
        }

        return ['path' => $path, 'width' => $image['width'], 'height' => $image['height'], 'mime' => $image['mime'], 'size' => strlen($image['contents']), 'signature' => $variant->signature()];
    }
}
