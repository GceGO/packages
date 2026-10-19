# Authorization, tenancy and privacy

## Reading and changing notifications

`ViewerResolver` builds the viewer on the server from:

- the panel's user, or the default guard's;
- the gcego/security tenant;
- the gcego/panels panel.

`DatabaseNotificationRepository::query()` always applies:

```
notifiable_type = viewer type AND notifiable_id = viewer key AND type = 'gcego.notification'
AND (gcego_expires_at IS NULL OR gcego_expires_at > now)
AND (gcego_tenant IS NULL OR gcego_tenant = viewer tenant)        -- tenant-less only, outside a tenant
[AND (gcego_panel IS NULL OR gcego_panel = viewer panel)]         -- panels.filter
```

Every operation narrows this query and never widens it: find, mark read/unread, mark all read, delete. Browser-supplied ids must be UUIDs, at most 100 per action. A forged or foreign id finds nothing.

## Sending

| Check | When |
| --- | --- |
| the category exists | always |
| recipients are saved `Notifiable` models, deduplicated and bounded | always |
| membership of the notification's tenant | `security.authorize_recipients` and gcego/security tenancy |
| `security.delivery_policy` | configured |
| preferences, mandatory channels | `preferences.enabled` |
| expiry | always, again in queued jobs |

Senders are trusted server code: nothing from a request decides recipients, tenants, channels or categories.

## Links

`NotificationAction` URLs must be root-relative, `https`, or on `app.url`. They are checked when the notification is defined, again when stored notifications are read, and again in the browser for toasts. Destinations authorize users with their own rules (gcego/pages, gcego/modules, gcego/security), so a notification never grants access.

## Privacy

- Payload metadata is limited to 32 scalar values (4 KB). Use record references; never put student records, credentials or tokens in a notification.
- Broadcasts omit metadata altogether.
- Delivery tracking stores a recipient reference and the status, never content.
- `gcego:notifications:prune` deletes expired notifications and, with an explicit retention period, old read ones. Nothing is deleted by default.
