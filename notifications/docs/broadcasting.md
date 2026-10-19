# Broadcasting and Laravel Reverb

gcego/notifications uses Laravel broadcasting. It never runs its own WebSocket server, and Reverb is the expected broadcaster.

## Setup

1. Run `php artisan install:broadcasting`. It installs Reverb and Echo, and registers `/broadcasting/auth` and `routes/channels.php`.
2. In `.env`, set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` keys. Run `php artisan reverb:start` and a queue worker; broadcasts are queued.
3. Make `window.Echo` available on panel pages.
4. Set `gcego-notifications.broadcasting.enabled` to `true`.

The package then registers `gcego.notifications.{hash}` with `NotificationChannelAuthorizer` once the application has booted. `NotificationInspector` reports `broadcasting.driver` or `broadcasting.channel` when something is missing.

## Channel security

| Threat | Protection |
| --- | --- |
| guessing another user's channel | the name is `HMAC-SHA256(app.key, "<morph>:<key>")` (40 hex), not the user id |
| subscribing to it anyway | `/broadcasting/auth` recomputes the hash for the authenticated user (`hash_equals`); others and guests get 403 |
| reading private data from a broadcast | the payload carries only display data and links; no metadata, recipients or models |
| other tenants or panels of the same user | the payload carries `tenant` and `panel`; the browser ignores mismatches, and the inbox query is scoped anyway |

## In the browser

The toast script runs once per page. It:

- subscribes once per channel (`Echo.private(channel).notification(...)`);
- accepts only `type === "gcego.notification"`;
- turns broadcasts into a `gcego-notification-received` window event, which the toast region shows (ignoring ids it has seen) and which refreshes notification centers;
- re-emits `gcego-notifications-refresh` after Pusher-protocol reconnections, so centers reload anything missed.

## Without real time

- The inbox, the center and toasts never depend on the socket.
- When broadcasting is off, `broadcast()` is reported `skipped: broadcasting_disabled`.
- `broadcasting.poll_seconds` (at least 10, default off) refreshes the center, only while it is visible.
