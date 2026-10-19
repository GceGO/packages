# API reference

| Class | Purpose |
| --- | --- |
| `Notification` | definition; static `make()`, `success()`, `error()`, `warning()`, `info()`, `fromActionResult()`; `send()`, `sendTo()`, `title()`, `body()`, `notificationType()`, `priority()`, `category()`, `icon()`, `channels()`, `metadata()`, `actions()`, `styleSlots()` |
| `Builders\NotificationBuilder` | `name`, `title`, `body`, `translate`, `type`/`success`/`error`/`warning`/`info`/`neutral`, `priority`/`critical`, `category`, `icon`, `metadata`, `action`, `actionToPage`, `toast`/`database`/`broadcast`/`mail`/`channels`, `tenant`/`global`, `panel`, `queue`/`now`, `sendAt`, `afterCommit`, `idempotencyKey`, `expiresAt`, `build`, `options`, `send`, `sendTo` |
| `Definitions\NotificationAction` | `Label`, `Url`, `Primary`, `NewTab` |
| `Definitions\NotificationType`, `NotificationPriority` | enums |
| `Definitions\NotificationCategory`, `NotificationCategories` | `register()`, `has()`, `get()`, `all()` |
| `Definitions\NotificationPayload` | `of()`, `fromArray()`, `toArray()`, `displayTitle()`, `displayBody()`, `isAssertive()` |
| `Dispatching\DeliveryOptions` | tenant, global, panel, queue, connection, queueName, delay, afterCommit, idempotencyKey, expiresAt |
| `Dispatching\DispatchResult`, `Delivery`, `DeliveryStatus` | `summary()`, `failed()`, `channel()`, `with()`, `successful()`, `throw()`, `merge()` |
| `NotificationManager` | `send()`, `unreadCount()`, `inbox()`, `markAsRead()`, `markAllAsRead()`, `category()`, `preferences()`, `inspect()` |
| `Database\DatabaseNotificationRepository` | `query()`, `page()`, `recent()`, `find()`, `unreadCount()`, `markRead()`, `markUnread()`, `markAllRead()`, `delete()` |
| `Database\NotificationQuery` | `fromInput($status, $category, $from, $until)` |
| `Database\NotificationViewer`, `ViewerResolver` | the current reader |
| `Preferences\NotificationPreferenceManager` | `allows()`, `set()`, `changeable()`, `preferences()` |
| `Contracts\NotificationDeliveryPolicy` | `allows(Model, NotificationPayload, DeliveryContext): bool` |
| `Contracts\NotificationDeliveryTracker` | `record(Delivery)` |
| `Broadcasting\NotificationChannels`, `NotificationChannelAuthorizer`, `NotificationBroadcastPayload` | private channels |
| `Livewire\ToastHost` (`gcego-toasts`), `NotificationCenter` (`gcego-notification-center`, `inboxUrl`), `NotificationInbox` (`gcego-notification-inbox`), `NotificationPreferences` (`gcego-notification-preferences`) | UI |
| `Integration\PanelNotificationsExtension` | gcego/panels bell, toasts and inbox route |
| `Diagnostics\NotificationInspector` | `inspect(): NotificationReport` (`healthy()`, `codes()`, `toArray()`) |
| `Console\PruneNotificationsCommand` | `gcego:notifications:prune` |
| `Exceptions\*` | `InvalidNotificationException`, `NotificationDeliveryException`, `NotificationAuthorizationException`; all implement `NotificationException` |
