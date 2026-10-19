<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;

/**
 * Unread counts, optionally cached (gcego-notifications.database.unread_cache_ttl).
 * Keys always include the user, tenant and panel, plus a per-user version
 * bumped whenever that user's notifications change, so a count is never
 * shared between users or served stale after a change.
 */
final readonly class UnreadCounts
{
    public function __construct(private NotificationsConfig $config, private Cache $cache) {}

    /**
     * @param  \Closure(): int  $count
     */
    public function count(NotificationViewer $viewer, \Closure $count): int
    {
        if ($this->config->unreadCacheTtl === 0) {
            return $count();
        }

        $key = 'gcego-notifications:unread:'.hash('sha256', implode('|', [$viewer->reference(), $viewer->tenant ?? '', $viewer->panel ?? '', $this->version($viewer->user)]));
        $cached = $this->cache->remember($key, $this->config->unreadCacheTtl, $count);

        return is_int($cached) ? $cached : $count();
    }

    public function forget(Model $user): void
    {
        if ($this->config->unreadCacheTtl === 0) {
            return;
        }

        $this->cache->forever($this->versionKey($user), $this->version($user) + 1);
    }

    private function version(Model $user): int
    {
        $version = $this->cache->get($this->versionKey($user));

        return is_int($version) ? $version : 0;
    }

    private function versionKey(Model $user): string
    {
        return 'gcego-notifications:unread-version:'.hash('sha256', Recipients::reference($user));
    }
}
