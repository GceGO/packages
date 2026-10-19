# Architecture

```
Notification::make()->…->sendTo($users)          Notification::success('…')
        │ build(): immutable Notification + DeliveryOptions
        ▼
NotificationManager::send() ──► NotificationDispatcher (scoped)
        │ 1 validate + category       2 channels (declared / defaults)      3 context: tenant, panel, key, expiry
        │ 4 toast ──► ToastQueue (session) ──► ToastHost (next page) or ToastResponseHook (Livewire response)
        │ 5 per recipient: RecipientAuthorizer (tenant membership, delivery policy) → preferences → idempotency
        │ 6 DeliveredNotification ──► Laravel ChannelManager ──► DatabaseChannel · broadcast (Reverb) · mail
        │       queued: one SendQueuedNotifications job per channel (after commit; re-checked by shouldSend)
        │       now: channel by channel (deferred with afterCommit inside a transaction)
        ▼ 7 DispatchResult + NotificationDeliveryTracker
Readers: ViewerResolver (user, tenant, panel) ──► DatabaseNotificationRepository ──► center · inbox · unread counts
```

| Piece | Lifetime | Notes |
| --- | --- | --- |
| `Notification`, `NotificationPayload`, `DeliveryOptions` | values | no queries, no delivery on construction |
| `NotificationDispatcher`, `RecipientAuthorizer`, `NotificationPreferenceManager`, `ViewerResolver` | scoped | read the current user, tenant and panel; per-request memo |
| `DatabaseNotificationRepository`, `UnreadCounts`, `NotificationChannels`, `ToastQueue` | singletons | stateless (state lives in the database, cache or session) |
| `DeliveredNotification` | per recipient | scalar payload and context only: safe to queue |

## What it reuses

| Need | Reused |
| --- | --- |
| storage, read state | Laravel's `notifications` table and `DatabaseNotification` |
| queueing, retries, delays, after commit, locale | Laravel Notifications (`ShouldQueue`, `Queueable`, `shouldSend`, `locale`) |
| real time | Laravel broadcasting (Reverb), private channels, Echo |
| mail | Laravel mail notifications |
| tenancy | gcego/security `TenantContext` and `TenantAuthorizer` |
| panel UI | gcego/panels `PanelExtension`, `PanelManager::respond()` |
| classes, icons | gcego/chroma slots and merger, the gcego/tables icon set |
| action results | gcego/actions' `gcego-action-completed` browser event |

## Extension points

| Contract or hook | For |
| --- | --- |
| `Contracts\NotificationDeliveryPolicy` | the application's recipient rules (and future gcego/settings, gcego/workflows) |
| `Contracts\NotificationDeliveryTracker` | sending outcomes to audit logs or analytics (gcego/analytics) |
| `NotificationManager::category()` | packages registering their categories (gcego/media, gcego/workflows) |
| `DeliveryOptions` (`delay`, `idempotencyKey`, `expiresAt`) | scheduled and workflow-driven delivery from Laravel's scheduler or gcego/workflows |
| `NotificationInspector` / `NotificationReport::toArray()` | gcego/cli health commands |
| `DispatchResult`, `Delivery` | gcego/testing assertions |
| `Livewire` components and published views | custom notification UIs |

gcego/studio detects the package through its descriptor (capabilities `notifications.*`) and lists it as an optional capability; panels get the inbox through `PanelNotificationsExtension`.
