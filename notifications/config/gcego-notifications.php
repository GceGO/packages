<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Notifications
|--------------------------------------------------------------------------
|
| Notifications are delivered through Laravel Notifications (database,
| broadcast and mail channels, queues, locales) plus toasts in the current
| session. Recipients only ever see their own notifications, in the tenant
| they are in. Broadcasting is off until the application has configured a
| broadcaster (Laravel Reverb) and the private channel below.
|
*/

return [

    // Channels of Notification::make()->sendTo() when none is chosen: toast, database, broadcast, mail.
    'default_channels' => ['database'],

    'toasts' => [
        'enabled' => true,
        // top-right, top-center, bottom-right or bottom-center.
        'position' => 'top-right',
        // Milliseconds; 0 keeps toasts until dismissed. Errors and critical toasts stay longer / until dismissed.
        'duration' => 5000,
        'error_duration' => 8000,
        'max_visible' => 4,
        // Show a toast for every gcego/actions result shown by an action host (success, failure, denial).
        'action_results' => true,
    ],

    'database' => [
        'enabled' => true,
        'per_page' => 20,
        // Notifications shown in the notification center dropdown.
        'recent' => 8,
        // Seconds an unread count may be cached per user, tenant and panel (0: never cached).
        'unread_cache_ttl' => 0,
        // Let users delete their own notifications from the inbox.
        'deletable' => true,
        // Days after which read notifications may be pruned by gcego:notifications:prune (null: never).
        'retention_days' => null,
    ],

    'broadcasting' => [
        // Opt in once a broadcaster (Reverb) and Laravel Echo are configured.
        'enabled' => false,
        // Private channel prefix; the user part is a keyed hash, never a raw user id.
        'channel' => 'gcego.notifications',
        // Seconds between background refreshes when real time is unavailable (0: no polling).
        'poll_seconds' => 0,
    ],

    'mail' => [
        'enabled' => true,
    ],

    'queue' => [
        // Queue database, broadcast and mail delivery (toasts are always immediate).
        'enabled' => true,
        'connection' => null,
        'queue' => 'notifications',
        'tries' => 3,
        // Seconds between attempts.
        'backoff' => [10, 60, 300],
        // Deliver only after the surrounding database transaction commits.
        'after_commit' => true,
    ],

    'recipients' => [
        // Recipients per sendTo() call (larger lists must be chunked by the caller).
        'max' => 1000,
        // Model attributes read for the recipient's locale and timezone (when present).
        'locale_attribute' => 'locale',
        'timezone_attribute' => 'timezone',
    ],

    'preferences' => [
        'enabled' => true,
        // channel => enabled when the user has no preference.
        'defaults' => ['database' => true, 'broadcast' => true, 'mail' => true],
    ],

    // Categories beyond the built-in "general", "system", "account" and "security":
    // id => ['label' => 'Translation key or text', 'mandatory' => ['database']]
    // "mandatory" channels are always delivered, whatever the user prefers (trusted policy).
    'categories' => [],

    'security' => [
        // Verify each recipient's membership of the notification's tenant before delivery.
        'authorize_recipients' => true,
        // Show users only notifications of their current tenant (and tenant-less ones).
        'enforce_tenant_scope' => true,
        // A class implementing Gcego\Notifications\Contracts\NotificationDeliveryPolicy (null: none).
        'delivery_policy' => null,
    ],

    'tracking' => [
        // Record per-channel delivery status (queued, sent to the provider, failed, skipped)
        // in gcego_notification_deliveries.
        'enabled' => false,
    ],

    'panels' => [
        // Add the bell, toasts and the inbox route ("<panel>/notifications") to every gcego/panels panel.
        'enabled' => true,
        // Show only notifications sent to the current panel (and panel-less ones) in each panel.
        'filter' => false,
    ],

];
