<?php

declare(strict_types=1);

namespace Gcego\Notifications\Delivery;

use Gcego\Notifications\Contracts\NotificationDeliveryTracker;
use Gcego\Notifications\Dispatching\Delivery;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

/**
 * The default tracker: rows in gcego_notification_deliveries when
 * gcego-notifications.tracking.enabled, nothing otherwise. A tracking
 * failure is reported, never allowed to break delivery.
 */
final readonly class DatabaseDeliveryTracker implements NotificationDeliveryTracker
{
    public const string Table = 'gcego_notification_deliveries';

    public function __construct(private NotificationsConfig $config, private ConnectionResolverInterface $connections) {}

    public function record(Delivery $delivery): void
    {
        if (! $this->config->tracking || preg_match('/^[0-9a-f-]{36}$/i', $delivery->notification) !== 1) {
            return;
        }

        try {
            $this->connections->connection()->table(self::Table)->insert([
                'notification_id' => $delivery->notification,
                'recipient' => $delivery->recipient === null ? null : mb_substr($delivery->recipient, 0, 120),
                'channel' => $delivery->channel,
                'status' => $delivery->status->value,
                'reason' => $delivery->reason === null ? null : mb_substr($delivery->reason, 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
