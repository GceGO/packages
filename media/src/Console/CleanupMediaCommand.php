<?php

declare(strict_types=1);

namespace Gcego\Media\Console;

use Gcego\Media\Attachments\MediaReplacer;
use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Storage\StoragePathResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * php artisan gcego:media:cleanup [--hours=24] [--dry-run] [--files]
 *
 * Purges (files and records) media that was soft-deleted, or that is
 * attached to nothing in a collection whose unattached media is not kept
 * (gcego-media.cleanup.keep_unattached), older than --hours. Shared or
 * attached media is never touched. --files lists files under the media
 * prefix that have no record (it never deletes unknown files).
 */
final class CleanupMediaCommand extends Command
{
    protected $signature = 'gcego:media:cleanup
        {--hours= : Only media older than this many hours (default: gcego-media.cleanup.unattached_after_hours)}
        {--dry-run : Report without deleting}
        {--files : Also list stored files that have no media record}';

    protected $description = 'Purge deleted and orphaned gcego media (never attached or shared media)';

    public function handle(MediaConfig $config, MediaReplacer $replacer, MediaStorage $storage, StoragePathResolver $paths): int
    {
        $hours = is_numeric($option = $this->option('hours')) && (int) $option > 0 ? (int) $option : $config->unattachedHours;
        $before = now()->subHours($hours);
        $query = static fn (): Builder => MediaRecord::withTrashed()
            ->where(static function (Builder $query) use ($before, $config): void {
                $query->where(static fn (Builder $query): Builder => $query->whereNotNull('deleted_at')->where('deleted_at', '<', $before))
                    ->orWhere(static fn (Builder $query): Builder => $query->whereNull('deleted_at')->where('updated_at', '<', $before)->whereNotIn('collection', $config->keepUnattached)->whereDoesntHave('attachments'));
            });

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('%d media would be purged.', $query()->count()));
        } else {
            $purged = 0;

            foreach ($query()->lazyById(200) as $media) {
                $purged += $replacer->purge($media) ? 1 : 0;
            }

            $this->components->info(sprintf('%d media purged.', $purged));
        }

        if ($this->option('files')) {
            foreach ($config->disks as $disk) {
                $known = array_flip(array_values(array_filter(MediaRecord::withTrashed()->where('disk', $disk)->pluck('path')->all(), is_string(...))));
                $unknown = 0;

                foreach ($storage->disk($disk)->allFiles($config->pathPrefix) as $file) {
                    if (! isset($known[$file]) && ! str_contains($file, '/variants/') && $paths->isManaged($file)) {
                        $unknown++;
                        $this->line(sprintf('  %s: %s', $disk, $file));
                    }
                }

                $this->components->info(sprintf('%s: %d files without a media record (not deleted).', $disk, $unknown));
            }
        }

        return self::SUCCESS;
    }
}
