<?php

declare(strict_types=1);

namespace Gcego\Media\Console;

use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Uploads\UploadManager;
use Illuminate\Console\Command;

/**
 * php artisan gcego:media:regenerate [--collection=] [--failed]
 *
 * Queues processing again (metadata and variants, regenerated) in chunks.
 */
final class RegenerateMediaCommand extends Command
{
    protected $signature = 'gcego:media:regenerate {--collection= : Only this collection} {--failed : Only media whose processing failed}';

    protected $description = 'Queue gcego media processing again (variants are regenerated)';

    public function handle(UploadManager $uploads): int
    {
        $query = MediaRecord::query();

        if (is_string($collection = $this->option('collection')) && $collection !== '') {
            $query->where('collection', $collection);
        }

        if ($this->option('failed')) {
            $query->where('status', 'failed');
        }

        $queued = 0;

        foreach ($query->lazyById(200) as $media) {
            $uploads->dispatch($media, true);
            $queued++;
        }

        $this->components->info(sprintf('%d media queued for processing.', $queued));

        return self::SUCCESS;
    }
}
