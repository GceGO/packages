# Changelog

## Unreleased

- **Added:** `NotificationBuilder::icon()` takes a typed Luminaries icon.

## 0.1.0 - 2026-10-17

First release.

- **Definitions:** `Notification` (a gcego/morfaw definition, with `success/error/warning/info()`, `make()`, `fromActionResult()`, `send()` and `sendTo()`), `NotificationBuilder`, `NotificationAction` (checked URLs), `NotificationType`, `NotificationPriority`, `NotificationCategory`/`NotificationCategories`, `NotificationPayload` and `DeliveryOptions`.
- **Dispatching:** `NotificationDispatcher`. It resolves channels, the tenant, the panel, recipient authorization (`RecipientAuthorizer`: tenant membership plus `Contracts\NotificationDeliveryPolicy`), preferences, expiry and idempotency. It sends queued, immediate or after-commit, and returns per-recipient, per-channel `DispatchResult`s (pending, queued, sent, failed, skipped with a reason).
- **Laravel integration:** `DeliveredNotification`/`QueuedDeliveredNotification`, the database channel with indexed gcego columns, broadcasts on HMAC-named private channels (`NotificationChannels`, `NotificationChannelAuthorizer`), mail in the recipient's locale, retries, backoff, delays and re-checks when queued jobs run.
- **Toasts:** a session-backed `ToastQueue`, the Livewire response hook and `ToastHost` (`<livewire:gcego-toasts />`). Live regions, pause on hover and focus, dismiss, actions, Echo subscription with duplicate prevention, and gcego/actions result toasts.
- **Inbox:** `DatabaseNotificationRepository` (viewer, tenant, panel and expiry scoped), `UnreadCounts` (optional versioned cache), `NotificationCenter`, `NotificationInbox` and `NotificationPreferences` Livewire components.
- **Preferences:** `NotificationPreferenceManager` (mandatory channels, most specific choice, defaults).
- **Delivery tracking:** `Contracts\NotificationDeliveryTracker` and `DatabaseDeliveryTracker`.
- **Panels:** `PanelNotificationsExtension` adds the bell, the toasts and the `<panel>/notifications` inbox route, with optional per-panel filtering.
- **Maintenance:** `NotificationInspector`, the `gcego:notifications:prune` command, migrations, styling slots and presets, and English and French translations.
