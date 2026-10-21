# gcego/notifications

Notifications for GCEGO Admin Studio: toasts, a notification center and inbox, private real-time broadcasting over Laravel Reverb, mail, preferences, and delivery tracking.

```php
use Gcego\Notifications\Notification;

Notification::success('Subject created successfully.');            // a toast for the current user

Notification::make()
    ->title('New Institution Request')
    ->body('An institution registration requires review.')
    ->database()
    ->sendTo($administrator);                                      // the administrator's inbox
```

It builds on what already exists and adds no second engine:

| Concern | Who does it |
| --- | --- |
| Storage | Laravel Notifications and the standard `notifications` table |
| Delivery | Laravel's queue, broadcasting (Reverb) and mail |
| Tenancy and recipient checks | gcego/security |
| Bell, toasts and inbox in panels | gcego/panels' extension point |
| Look | gcego/chroma |
| Action results | gcego/actions' result event |

## Contents

- [Requirements and installation](#requirements-and-installation)
- [Examples](#examples)
- [Toasts](#toasts)
- [Database notifications, the center and the inbox](#database-notifications-the-center-and-the-inbox)
- [Types, priorities, categories and actions](#types-priorities-categories-and-actions)
- [Channels, queues and delivery results](#channels-queues-and-delivery-results)
- [Broadcasting and Laravel Reverb](#broadcasting-and-laravel-reverb)
- [Preferences and delivery policy](#preferences-and-delivery-policy)
- [Panels, pages and actions](#panels-pages-and-actions)
- [Tenancy, security and privacy](#tenancy-security-and-privacy)
- [Accessibility](#accessibility)
- [Diagnostics and maintenance](#diagnostics-and-maintenance)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Requirements and installation

You need:

- PHP 8.3+, Laravel 13 and Livewire 4;
- the GCEGO packages 01–18 (gcego/panels for the panel UI);
- for real time: Laravel Reverb (or any Laravel broadcaster) and Laravel Echo on the page.

```bash
composer require gcego/notifications
php artisan migrate                                               # adds columns to "notifications", and two tables
php artisan vendor:publish --tag=gcego-notifications-config       # optional
```

The migrations change the database in three ways:

- They reuse Laravel's `notifications` table, and create it only when it is missing.
- They add nullable `gcego_tenant`, `gcego_panel`, `gcego_category`, `gcego_key` and `gcego_expires_at` columns, with indexes. Existing rows are untouched.
- They create `gcego_notification_preferences` and `gcego_notification_deliveries` (tracking is off by default).

Recipients are Eloquent models using Laravel's `Notifiable` trait.

Inside gcego/panels panels, the bell, the toasts and `<panel>/notifications` appear on their own. Elsewhere, add the components to your layout:

```blade
<livewire:gcego-toasts />
<livewire:gcego-notification-center />
<livewire:gcego-notification-inbox />
<livewire:gcego-notification-preferences />
```

## Examples

A toast for each type:

<!-- example:toasts -->
```php
Notification::success('Record created successfully.');
Notification::error('Unable to save the record.');
Notification::warning('This action requires confirmation.');
Notification::info('Your report is being prepared.');
```

Persistent and real-time delivery:

<!-- example:realtime -->
```php
Notification::make()
    ->title('New Message')
    ->body('You have received a message.')
    ->database()
    ->broadcast()
    ->sendTo($recipient);
```

A link, sent once, to many recipients, after the transaction commits:

<!-- example:report -->
```php
Notification::make()
    ->title('Report Ready')
    ->body('Your requested report is available.')
    ->category('system')
    ->action('View report', '/admin/reports')
    ->idempotencyKey('report.42.ready')
    ->database()
    ->mail()
    ->sendTo([$administrator, $recipient]);
```

The declarative form, a gcego/morfaw definition:

<!-- example:declarative -->
```php
$notification = new Notification(
    new Property(Notification::Title, 'Operation Completed'),
    new Property(Notification::Body, 'The requested action completed successfully.'),
    new Property(Notification::Type, 'success'),
    new Property(Notification::Channels, ['database']),
);

$notification->sendTo($recipient);
```

`send()` and `sendTo()` return a `DispatchResult` (see [Channels](#channels-queues-and-delivery-results)).

## Toasts

Toasts are for the current user's session. `Notification::success/error/warning/info()` and `->toast()->send()` produce them. They survive redirects: they wait in the session until a page with `<livewire:gcego-toasts />` shows them. During a Livewire action they arrive with the response, unless that response redirects, in which case they wait for the next page.

| | |
| --- | --- |
| Placement | `toasts.position`: `top-right`, `top-center`, `bottom-right`, `bottom-center` |
| Duration | `toasts.duration` (5 s); errors `toasts.error_duration` (8 s); critical toasts stay until dismissed |
| Stacking | at most `toasts.max_visible` (4) |
| Timers | pause while a toast is hovered or focused |
| Dismissing | a dismiss button; no server request |
| Rendering | text through `x-text`, never as HTML; links checked on the server and again in the browser |
| One region per page | a second `<livewire:gcego-toasts />` renders nothing; listeners register once |

`sendTo()` with `->toast()` shows a toast only when the current user is a recipient.

## Database notifications, the center and the inbox

`->database()` stores the notification through Laravel's database channel. Each reader only ever sees their own notifications, in their current tenant (see [Tenancy](#tenancy-security-and-privacy)).

| Component | What it does |
| --- | --- |
| `gcego-notification-center` | The bell. Its accessible name includes the unread count ("Notifications, 3 unread"). The dropdown loads when first opened and lists recent items with time, category and links, plus mark read, mark all read and view all. |
| `gcego-notification-inbox` | Every notification, filtered by status, category and dates, paginated on the server. Supports selection with bulk mark read, mark unread and delete (`database.deletable`). |
| `gcego-notification-preferences` | Switches per category and channel. |

The same operations are available in PHP:

```php
app(NotificationManager::class)->unreadCount();
app(NotificationManager::class)->markAsRead($id);
app(NotificationManager::class)->markAllAsRead();
app(NotificationManager::class)->inbox(NotificationQuery::fromInput('unread', 'system'));   // paginator
```

Unread counts cost one indexed query. With `database.unread_cache_ttl` they are cached per user, tenant and panel, and invalidated whenever that user's notifications change.

## Types, priorities, categories and actions

| Concept | Values | Effect |
| --- | --- | --- |
| Type (`success()`, `error()`, `warning()`, `info()`, `neutral()`) | presentation | icon, color, screen-reader label |
| Priority (`priority('high')`, `critical()`) | low, normal, high, critical | ordering hints; critical toasts stay; high or critical errors are announced assertively. **It never widens delivery or overrides preferences.** |
| Category (`category('system')`) | `general`, `system`, `account`, `security`, plus `gcego-notifications.categories` and `NotificationManager::category()` | inbox filter and preferences; unknown categories are refused |
| Actions (`action($label, $url)`, `actionToPage($label, $page, $record, $panel)`) | at most 3 links | root-relative, `https`, or this application's URL |

Opening an action from the center marks the notification read. Destination pages authorize the user again, so a notification never grants access.

To translate a notification, make its title and body translation keys and call `->translate(['name' => 'Chemistry'])`. Each reader sees it in their locale, and mail uses the recipient's locale (`HasLocalePreference` or the `locale` attribute). Times are stored once and shown relative and absolute in the reader's timezone (their `timezone` attribute, else the application's).

## Channels, queues and delivery results

| Channel | Through | Notes |
| --- | --- | --- |
| `toast()` | the session | immediate |
| `database()` | Laravel's database channel | the inbox |
| `broadcast()` | Laravel broadcasting | off until `broadcasting.enabled` |
| `mail()` | Laravel mail | the recipient's `routeNotificationForMail()`; recipients without an address are skipped, not reported "sent" |

`sendTo()` uses `default_channels` when you choose none.

Delivery is queued by default:

- **Jobs:** Laravel's `SendQueuedNotifications`, one job per channel, on `queue.queue` (`notifications`), with `queue.tries` and `queue.backoff`.
- **After commit:** inside a transaction, delivery waits for the commit and never happens for a rollback (`queue.after_commit`). Immediate sends (`->now()`) wait the same way.
- **Scheduling:** `->sendAt($date)` schedules with a queue delay, and `->expiresAt($date)` hides the notification afterwards.
- **Re-checks:** when a queued job runs, expiry, tenant membership, the delivery policy and preferences are checked again.

`->idempotencyKey('invoice.42.paid')` delivers at most once per recipient and key. A cache claim and a unique index enforce it, and a rolled-back transaction does not use up the key. Repeated notifications without a key are delivered normally.

`sendTo()` accepts a model or a list. Each recipient is delivered once, with at most `recipients.max` (1000) per call; chunk larger audiences. Recipient lists are never put in payloads.

```php
$result = Notification::make()->title('Staff meeting')->database()->sendTo($staff);

$result->summary();      // ['queued' => 12, 'skipped' => 1]
$result->failed();       // list<Delivery>
$result->throw();        // NotificationDeliveryException when any delivery failed
```

| Status | Meaning |
| --- | --- |
| `pending` | waiting for the transaction to commit |
| `queued` | a job was accepted by the queue; **not** a delivery |
| `sent` | the channel accepted it (stored, broadcast, handed to the mailer); not a read receipt |
| `failed` | it failed (reported to your exception handler) |
| `skipped` | not attempted, with a reason: `preference`, `tenant`, `policy`, `expired`, `duplicate`, `no_address`, `disabled`, `broadcasting_disabled`, `no_recipient`, `no_session`, `not_current_user` |

With `tracking.enabled`, every outcome is also stored in `gcego_notification_deliveries`, including queued jobs that later send or fail. Bind your own `Contracts\NotificationDeliveryTracker` to send outcomes elsewhere.

## Broadcasting and Laravel Reverb

Broadcasting is opt-in. Set it up in four steps:

1. Configure Reverb: `php artisan install:broadcasting`, `BROADCAST_CONNECTION=reverb`.
2. Load Laravel Echo on your pages (`window.Echo`).
3. Set `gcego-notifications.broadcasting.enabled` to `true`.
4. Make sure `/broadcasting/auth` runs under your `web` middleware. Laravel registers it with `install:broadcasting` / `withBroadcasting()`.

Each recipient gets a private channel `gcego.notifications.<hash>`. The hash is an HMAC of the recipient reference, keyed with the application key, so channel names reveal no user id and cannot be guessed. Subscribing is authorized by recomputing the hash for the authenticated user, so another user, a guest or a made-up channel is refused.

The broadcast carries:

- the id, title, a body trimmed to 300 characters, the type (`notification_type`), priority, category, icon and links;
- the tenant and panel, so the browser ignores other workspaces;
- a timestamp.

It never carries metadata, recipients or models. The toast region subscribes once and ignores duplicates. Notification centers refresh when a notification arrives and after a reconnection.

When Reverb is down or not configured:

- the database inbox, the center and toasts keep working;
- `broadcast()` is reported as `skipped: broadcasting_disabled`;
- you can set `broadcasting.poll_seconds` (at least 10) for a bounded refresh of the center.

## Preferences and delivery policy

Users choose channels per category in `<livewire:gcego-notification-preferences />`. The component only ever writes the signed-in user's own choices.

The decision for each recipient and channel goes in this order:

1. A category's **mandatory** channels (`categories.<id>.mandatory`, trusted configuration) are always delivered.
2. Otherwise, the most specific stored choice wins: tenant and category, then tenant, then category, then all.
3. Otherwise, `preferences.defaults` apply.

Toasts are not preferences: they answer the user's own actions.

```php
app(NotificationManager::class)->preferences()->set($user, 'mail', false, 'system');
```

The application's own rule, `security.delivery_policy`, is a `Contracts\NotificationDeliveryPolicy`. It runs for every recipient after the tenant membership check, at send time and again when a queued job runs:

```php
final class OnlyActiveAccounts implements NotificationDeliveryPolicy
{
    public function allows(Model $recipient, NotificationPayload $payload, DeliveryContext $context): bool
    {
        return $recipient->getAttribute('suspended_at') === null;
    }
}
```

## Panels, pages and actions

In every gcego/panels panel (via `Gcego\Panels\Contracts\PanelExtension`):

- the bell sits in the top bar;
- the toast region sits at the end of the document;
- the inbox is at `<panel>/notifications` (route `<namespace>notifications`, e.g. `gcego.admin.notifications`), behind the panel's authentication, tenancy and access checks.

A notification shows in every panel its recipient may enter. With `panels.filter`, a panel shows only notifications sent to it (`->panel('admin')`, or the current panel by default) and panel-less ones.

`actionToPage('View subject', 'academics.subjects.view', $subject->id, panel: 'admin')` links to a gcego/pages or gcego/modules page through its panel route.

**gcego/actions:** action hosts already dispatch `gcego-action-completed`. The toast region shows those results as toasts (`toasts.action_results`) without running anything again, and never creates persistent notifications for them. To notify someone about an action result, build a notification from it yourself:

```php
Notification::fromActionResult($result)->database()->sendTo($owner);
```

`ActionResult::success($action, $message)` is gcego/actions' factory. Its message becomes the title, and its status becomes the type.

## Tenancy, security and privacy

Who sees what:

- **Recipients:** a recipient sees only their own notifications. Every query starts from the signed-in user, and ids from the browser are only looked up inside that scope (at most 100 per bulk action). Another user's notification is never read, changed or deleted.
- **Tenants:** a notification belongs to the current gcego/security tenant, or `->tenant($key)`, or none with `->global()`. Readers see their current tenant's notifications and tenant-less ones; outside a tenant, tenant-less ones only. Before delivery, each recipient's membership of the tenant is verified (`security.authorize_recipients`); non-members are skipped.
- **Links:** they are checked when the notification is defined and again when stored notifications are read. The destination page authorizes on its own.

What is stored and sent:

- **Escaping:** all text is escaped in the center and inbox, and set as text in toasts.
- **Payloads:** they hold plain strings and at most 32 scalar metadata values (4 KB). Store record references and authorized links, not records or personal details. Broadcasts omit metadata altogether.
- **Livewire state:** it holds no models, users or payload secrets. The panel context stays on the server.

## Accessibility

| Area | Behaviour |
| --- | --- |
| Toast announcements | two live regions: `role="status"` (polite) for routine toasts, `role="alert"` (assertive) only for high or critical errors; each toast's type is announced first ("Success: …") |
| Toast controls | timers pause on hover and focus (WCAG 2.2.1); dismiss buttons are keyboard-operable and labelled |
| Notification center | a disclosure: `aria-expanded` and `aria-controls`; Escape closes it and returns focus to the bell; the unread count is in the bell's accessible name, and the visual badge is `aria-hidden` |
| Inbox and center items | read state and type are announced before the title; checkboxes and per-item buttons name their notification; filters are labelled in a search landmark; pagination is a labelled `nav` |
| Preferences | `role="switch"` buttons with `aria-checked` |
| Visuals | focus rings are visible; animations respect `prefers-reduced-motion`; colors are theme tokens (contrast and dark mode follow the theme) |

## Diagnostics and maintenance

```php
app(NotificationManager::class)->inspect()->toArray();
```

The report contains no notification content or credentials. It can flag:

| Code | Problem |
| --- | --- |
| `database.table`, `database.columns`, `preferences.table`, `tracking.table` | missing migrations |
| `queue.sync` | queued delivery on the sync connection |
| `broadcasting.driver`, `broadcasting.channel` | broadcasting enabled without Reverb, or without the private channel |
| `mail.driver` | a log or array mailer |
| `recipients.notifiable` | a user model without `Notifiable` |
| `panels.extension` | the panel extension is not registered |

```bash
php artisan gcego:notifications:prune [--days=90] [--tenant=1] [--dry-run]
```

The prune command deletes expired gcego notifications. With a retention period (`database.retention_days`, or `--days`) it also deletes read ones older than that. Unread notifications and other applications' notifications are never pruned. Schedule it with `Schedule::command('gcego:notifications:prune')->daily()`.

## Testing

```bash
composer test       # Pest (includes Node-run checks of the toast script)
composer analyse    # PHPStan (max)
composer format     # Pint
```

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No toasts | Is `<livewire:gcego-toasts />` (or a panel) on the page? `toasts.enabled`? Is there a session? |
| No bell | Signed in? `database.enabled`? In panels, `panels.enabled`? |
| Nothing in the inbox | A queue worker running (`queue.enabled`)? The right tenant (`->global()` for tenant-less)? |
| No real time | `inspect()` codes `broadcasting.driver` or `broadcasting.channel`; is `window.Echo` loaded? |
| Mail "skipped: no_address" | The recipient has no `email` (or `routeNotificationForMail()`). |

See [docs/](docs) for the architecture and the API reference.
