<?php

declare(strict_types=1);

namespace Gcego\Notifications;

use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\NotificationQuery;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\Diagnostics\NotificationInspector;
use Gcego\Notifications\Diagnostics\NotificationReport;
use Gcego\Notifications\Dispatching\DeliveryOptions;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Notifications\Dispatching\NotificationDispatcher;
use Gcego\Notifications\Preferences\NotificationPreferenceManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point for notifications:
 *
 *     app(NotificationManager::class)->send($notification, $recipients);   // DispatchResult
 *     app(NotificationManager::class)->unreadCount();                       // the current user's
 *     app(NotificationManager::class)->markAllAsRead();
 *     app(NotificationManager::class)->category(new NotificationCategory('academic', 'Academic'));
 *     app(NotificationManager::class)->inspect();                           // NotificationReport
 *
 * Reads and changes always concern the signed-in user. Services are
 * resolved per call, so the manager is safe in long-running workers.
 */
final readonly class NotificationManager
{
    public function __construct(private Container $container) {}

    /**
     * @param  Model|iterable<Model>|null  $recipients  null: the current user.
     */
    public function send(Notification $notification, Model|iterable|null $recipients = null, ?DeliveryOptions $options = null): DispatchResult
    {
        return $this->container->make(NotificationDispatcher::class)->dispatch($notification, $recipients, $options ?? new DeliveryOptions);
    }

    public function unreadCount(): int
    {
        $viewer = $this->container->make(ViewerResolver::class)->current();

        return $viewer === null ? 0 : $this->repository()->unreadCount($viewer);
    }

    /**
     * @return LengthAwarePaginator<int, Database\NotificationItem|null>|null
     */
    public function inbox(?NotificationQuery $query = null, int $page = 1): ?LengthAwarePaginator
    {
        $viewer = $this->container->make(ViewerResolver::class)->current();

        return $viewer === null ? null : $this->repository()->page($viewer, $query ?? new NotificationQuery, $page);
    }

    public function markAsRead(string ...$ids): int
    {
        $viewer = $this->container->make(ViewerResolver::class)->current();

        return $viewer === null ? 0 : $this->repository()->markRead($viewer, array_values($ids));
    }

    public function markAllAsRead(): int
    {
        $viewer = $this->container->make(ViewerResolver::class)->current();

        return $viewer === null ? 0 : $this->repository()->markAllRead($viewer);
    }

    public function category(NotificationCategory $category): void
    {
        $this->container->make(NotificationCategories::class)->register($category);
    }

    public function preferences(): NotificationPreferenceManager
    {
        return $this->container->make(NotificationPreferenceManager::class);
    }

    public function inspect(): NotificationReport
    {
        return $this->container->make(NotificationInspector::class)->inspect();
    }

    private function repository(): DatabaseNotificationRepository
    {
        return $this->container->make(DatabaseNotificationRepository::class);
    }
}
