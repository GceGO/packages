<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use Carbon\CarbonImmutable;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Laravel\DeliveredNotification;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The viewer's stored gcego notifications. Every query starts from the
 * viewer (recipient type and key), the tenant scope (the viewer's tenant
 * and tenant-less notifications; tenant-less only outside a tenant), the
 * optional panel filter and expiry. Ids from the browser are only ever
 * used inside that scope, so another user's notification is never found,
 * read, changed or deleted.
 */
final readonly class DatabaseNotificationRepository
{
    public const string IdPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public const int MaxBulk = 100;

    public function __construct(private NotificationsConfig $config, private UnreadCounts $counts) {}

    /**
     * @return Builder<DatabaseNotification>
     */
    public function query(NotificationViewer $viewer): Builder
    {
        $query = DatabaseNotification::query()
            ->where('notifiable_type', $viewer->type())
            ->where('notifiable_id', $viewer->key())
            ->where('type', DeliveredNotification::DatabaseType)
            ->where(static fn (Builder $query): Builder => $query->whereNull('gcego_expires_at')->orWhere('gcego_expires_at', '>', now()));

        if ($this->config->enforceTenantScope) {
            $query->where(static fn (Builder $query): Builder => $viewer->tenant === null
                ? $query->whereNull('gcego_tenant')
                : $query->whereNull('gcego_tenant')->orWhere('gcego_tenant', $viewer->tenant));
        }

        if ($this->config->panelFilter && $viewer->panel !== null) {
            $query->where(static fn (Builder $query): Builder => $query->whereNull('gcego_panel')->orWhere('gcego_panel', $viewer->panel));
        }

        return $query;
    }

    /**
     * Items are null only for rows whose data is not a gcego payload (corrupt or foreign).
     *
     * @return LengthAwarePaginator<int, NotificationItem|null>
     */
    public function page(NotificationViewer $viewer, NotificationQuery $filters, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->query($viewer);

        match ($filters->status) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        if ($filters->category !== null) {
            $query->where('gcego_category', $filters->category);
        }

        if ($filters->from !== null) {
            $query->where('created_at', '>=', $filters->from);
        }

        if ($filters->until !== null) {
            $query->where('created_at', '<=', $filters->until);
        }

        $paginator = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(min($perPage ?? $this->config->perPage, 100), ['id', 'data', 'read_at', 'created_at'], 'page', max(1, $page));

        return $paginator->through(static fn (DatabaseNotification $notification): ?NotificationItem => self::item($notification));
    }

    /**
     * @return list<NotificationItem>
     */
    public function recent(NotificationViewer $viewer, ?int $limit = null): array
    {
        $items = [];

        foreach ($this->query($viewer)->orderByDesc('created_at')->orderByDesc('id')->limit($limit ?? $this->config->recent)->get(['id', 'data', 'read_at', 'created_at']) as $notification) {
            $item = self::item($notification);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function find(NotificationViewer $viewer, string $id): ?NotificationItem
    {
        if (preg_match(self::IdPattern, $id) !== 1) {
            return null;
        }

        $notification = $this->query($viewer)->whereKey($id)->first(['id', 'data', 'read_at', 'created_at']);

        return $notification === null ? null : self::item($notification);
    }

    public function unreadCount(NotificationViewer $viewer): int
    {
        return $this->counts->count($viewer, fn (): int => $this->query($viewer)->whereNull('read_at')->count());
    }

    /**
     * @param  list<string>  $ids
     * @return int How many changed.
     */
    public function markRead(NotificationViewer $viewer, array $ids): int
    {
        return $this->change($viewer, $ids, static fn (Builder $query): int => $query->whereNull('read_at')->update(['read_at' => now()]));
    }

    /**
     * @param  list<string>  $ids
     */
    public function markUnread(NotificationViewer $viewer, array $ids): int
    {
        return $this->change($viewer, $ids, static fn (Builder $query): int => $query->whereNotNull('read_at')->update(['read_at' => null]));
    }

    public function markAllRead(NotificationViewer $viewer): int
    {
        $changed = $this->query($viewer)->whereNull('read_at')->update(['read_at' => now()]);
        $this->counts->forget($viewer->user);

        return $changed;
    }

    /**
     * @param  list<string>  $ids
     */
    public function delete(NotificationViewer $viewer, array $ids): int
    {
        if (! $this->config->deletable) {
            return 0;
        }

        return $this->change($viewer, $ids, static fn (Builder $query): int => is_int($deleted = $query->delete()) ? $deleted : 0);
    }

    /**
     * @param  list<string>  $ids
     * @param  \Closure(Builder<DatabaseNotification>): int  $change
     */
    private function change(NotificationViewer $viewer, array $ids, \Closure $change): int
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (mixed $id): bool => is_string($id) && preg_match(self::IdPattern, $id) === 1)));

        if ($ids === []) {
            return 0;
        }

        $changed = $change($this->query($viewer)->whereKey(array_slice($ids, 0, self::MaxBulk)));
        $this->counts->forget($viewer->user);

        return $changed;
    }

    private static function item(DatabaseNotification $notification): ?NotificationItem
    {
        $data = $notification->getAttribute('data');
        $payload = is_array($data) ? NotificationPayload::fromArray($data) : null;
        $created = $notification->getAttribute('created_at');
        $key = $notification->getKey();

        if ($payload === null || ! is_string($key)) {
            return null;
        }

        return new NotificationItem(
            $key,
            $payload,
            $notification->getAttribute('read_at') !== null,
            $created instanceof \DateTimeInterface ? CarbonImmutable::instance($created) : CarbonImmutable::now(),
        );
    }
}
