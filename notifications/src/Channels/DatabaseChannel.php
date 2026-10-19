<?php

declare(strict_types=1);

namespace Gcego\Notifications\Channels;

use Gcego\Notifications\Database\UnreadCounts;
use Gcego\Notifications\Laravel\DeliveredNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Channels\DatabaseChannel as LaravelDatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, storing gcego notifications in the standard
 * "notifications" table plus the columns gcego/notifications adds
 * (tenant, panel, category, idempotency key, expiry), so inbox queries
 * filter on indexed columns. A second delivery with the same idempotency
 * key for the same recipient is refused by a unique index and ignored.
 */
final class DatabaseChannel extends LaravelDatabaseChannel
{
    /**
     * @return Model|null
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            $stored = parent::send($notifiable, $notification);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($notifiable instanceof Model) {
            app(UnreadCounts::class)->forget($notifiable);
        }

        return $stored;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if (! $notification instanceof DeliveredNotification) {
            return $payload;
        }

        $context = $notification->context;

        return [
            ...$payload,
            'gcego_tenant' => $context['tenant'],
            'gcego_panel' => $context['panel'],
            'gcego_category' => is_string($notification->payload['category'] ?? null) ? $notification->payload['category'] : null,
            'gcego_key' => $context['key'],
            'gcego_expires_at' => $context['expires_at'] === null ? null : now()->parse($context['expires_at']),
        ];
    }
}
