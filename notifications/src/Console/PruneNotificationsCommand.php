<?php

declare(strict_types=1);

namespace Gcego\Notifications\Console;

use Gcego\Notifications\Laravel\DeliveredNotification;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * php artisan gcego:notifications:prune [--days=] [--tenant=] [--dry-run]
 *
 * Deletes gcego notifications that have expired and, only when a retention
 * period is configured (gcego-notifications.database.retention_days) or
 * given, read ones older than it. Unread notifications are never pruned by
 * age, and other notifications in the table are never touched. Runs in
 * chunks, so it can be scheduled (Schedule::command(...)->daily()).
 */
final class PruneNotificationsCommand extends Command
{
    protected $signature = 'gcego:notifications:prune
        {--days= : Delete read notifications older than this many days (default: the configured retention)}
        {--tenant= : Only this tenant\'s notifications}
        {--dry-run : Count without deleting}';

    protected $description = 'Delete expired gcego notifications and, with a retention period, old read ones';

    public function handle(NotificationsConfig $config): int
    {
        $days = $this->option('days');
        $days = is_numeric($days) && (int) $days > 0 ? (int) $days : $config->retentionDays;
        $tenant = $this->option('tenant');
        $tenant = is_string($tenant) && $tenant !== '' ? $tenant : null;

        $query = static function () use ($days, $tenant): Builder {
            $query = DatabaseNotification::query()
                ->where('type', DeliveredNotification::DatabaseType)
                ->where(static function (Builder $query) use ($days): void {
                    $query->where('gcego_expires_at', '<=', now());

                    if ($days !== null) {
                        $query->orWhere(static fn (Builder $query): Builder => $query->whereNotNull('read_at')->where('created_at', '<', now()->subDays($days)));
                    }
                });

            return $tenant === null ? $query : $query->where('gcego_tenant', $tenant);
        };

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('%d notifications would be deleted.', $query()->count()));

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = $query()->limit(1000)->pluck('id')->all();
            $count = $ids === [] ? 0 : DatabaseNotification::query()->whereKey($ids)->delete();
            $deleted += is_int($count) ? $count : 0;
        } while (count($ids) === 1000);

        $this->components->info(sprintf('%d notifications deleted.', $deleted));

        return self::SUCCESS;
    }
}
