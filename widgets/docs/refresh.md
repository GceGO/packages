# Refresh and caching

## Refresh

Without a `Refresh` node, widgets never poll: they refresh when the user
presses Refresh or changes the date range.

```php
new Refresh(every: 60)                         // wire:poll.60s.visible — only while visible
new Refresh(on: ['gcego-action-completed'])    // refresh on these browser events
```

| Rule | Details |
| --- | --- |
| Minimum interval | Intervals below `refresh.minimum_interval_seconds` (30) are raised to it. |
| No overlap | Livewire never overlaps requests of one component. |
| Re-checks | Every refresh re-resolves the widget, re-checks its requirements and re-reads the data as the current user. |

## Caching

Caching is opt-in per widget, and also needs `cache.enabled`.

```php
new Cache(ttl: 600)                    // per user (default)
new Cache(ttl: 600, scope: 'tenant')   // shared by the tenant's authorized users: only for user-independent data
```

| Rule | Details |
| --- | --- |
| Key | A hash of the widget, child, version, period, page, record, tenant and locale, plus the guard and user key for `user` scope. There are no shared global keys. |
| Authorization first | Authorization runs before the cache is read, so cached values are only served to permitted users. |
| What is stored | Only the `WidgetDataResult`, never users, models or contexts. |
| Invalidation | `WidgetManager::forget('widget:…')` bumps a version and invalidates every key of the widget. |
