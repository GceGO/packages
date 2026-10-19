<?php

declare(strict_types=1);

namespace Gcego\Media\Jobs;

use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Integration\MediaNotifier;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Transformations\MediaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Scan, metadata and variants of one media record, queued after the
 * upload commits. Unique per record (no duplicate processing); it carries
 * only the record id. Retried gcego-media.transformations.tries times;
 * the final failure is recorded on the record and reported.
 */
final class ProcessMedia implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $mediaId, public bool $force = false)
    {
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return 'gcego-media-'.$this->mediaId;
    }

    public function handle(MediaProcessor $processor, MediaNotifier $notifier): void
    {
        $media = MediaRecord::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        $processed = $processor->process($media, $this->force);

        if ($processed->status === MediaStatus::Ready) {
            $notifier->processed($processed);
        }
    }

    public function failed(Throwable $exception): void
    {
        $media = MediaRecord::query()->find($this->mediaId);

        if ($media !== null) {
            app(MediaNotifier::class)->failed($media);
        }
    }
}
