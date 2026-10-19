# Channels, queues and delivery

| Channel | Laravel channel | Skipped when |
| --- | --- | --- |
| `toast` | none (session) | toasts disabled, no session, or the current user is not a recipient |
| `database` | `Channels\DatabaseChannel` (extends Laravel's) | `database.enabled` off, preference, policy, tenant, expiry, duplicate |
| `broadcast` | Laravel `broadcast` | `broadcasting.enabled` off, plus the above |
| `mail` | Laravel `mail` | `mail.enabled` off, no address, plus the above |

## Queueing

`queue.enabled` (the default) sends a `QueuedDeliveredNotification`. Laravel dispatches one `SendQueuedNotifications` job per channel:

| Setting | Source |
| --- | --- |
| connection, queue | `->queue($queue, $connection)`, else `queue.connection` / `queue.queue` |
| tries, backoff | `queue.tries`, `queue.backoff` |
| delay | `->sendAt($date)` |
| after commit | `->afterCommit()`, else `queue.after_commit` |

When the job runs, `shouldSend()` checks:

- expiry;
- tenant membership;
- the delivery policy;
- preferences.

A refused delivery is tracked as skipped. A job that fails for good calls `failed()`, which is tracked with its channel.

`->now()` sends during the request, channel by channel. Inside a transaction, the send waits for the commit, and the idempotency key is claimed only then.

## Statuses

`DeliveryStatus`: `pending`, `queued`, `sent`, `failed`, `skipped`.

- `queued` is not a delivery.
- `sent` means the channel accepted it: stored, broadcast, or handed to the mailer.
- Nothing claims that a person received or read a notification. Read state is tracked separately, in `read_at`.

## Idempotency

`->idempotencyKey($key)` is scoped to the recipient and the key, so different keys and different recipients never collide. It is enforced twice:

1. a cache claim, until expiry or for 30 days, which skips the whole delivery;
2. the `notifications_gcego_key_unique` index, which makes a second database row impossible.

## Multi-recipient delivery

- Recipients are deduplicated, with at most `recipients.max` (1000) per call.
- Each recipient gets its own authorization, preferences, Laravel notification and id.
- One recipient's failure is recorded without stopping the others.
