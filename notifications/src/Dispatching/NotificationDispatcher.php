<?php

declare(strict_types=1);

namespace Gcego\Notifications\Dispatching;

use DateTimeImmutable;
use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Contracts\NotificationDeliveryTracker;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;
use Gcego\Notifications\Delivery\RecipientAuthorizer;
use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\Laravel\DeliveredNotification;
use Gcego\Notifications\Laravel\QueuedDeliveredNotification;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Notifications\Preferences\NotificationPreferenceManager;
use Gcego\Notifications\Toasts\Toast;
use Gcego\Notifications\Toasts\ToastQueue;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Notifications\Dispatcher as LaravelNotifications;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers a notification. The lifecycle, per send():
 *
 *  1. validate the definition and its category;
 *  2. resolve channels (declared, else toast for send() and
 *     gcego-notifications.default_channels for sendTo()) and drop disabled ones;
 *  3. resolve the context: tenant (explicit, else the current one), panel,
 *     idempotency key, expiry;
 *  4. toasts: queued in the current user's session;
 *  5. for each recipient (bounded): tenant membership and delivery policy,
 *     preferences per channel, expiry, idempotency;
 *  6. hand one Laravel notification per recipient to Laravel: queued
 *     (one job per channel, after commit when configured) or sent now,
 *     channel by channel (deferred to after the commit inside a transaction);
 *  7. record every outcome (DispatchResult and the delivery tracker).
 *
 * Failures are reported and recorded, never swallowed. Queued deliveries
 * re-check expiry, membership, policy and preferences when they run.
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationsConfig $config,
        private NotificationCategories $categories,
        private Recipients $recipients,
        private RecipientAuthorizer $authorizer,
        private NotificationPreferenceManager $preferences,
        private NotificationChannels $channels,
        private ToastQueue $toasts,
        private NotificationDeliveryTracker $tracker,
        private LaravelNotifications $notifications,
        private ConnectionResolverInterface $connections,
        private Cache $cache,
        private Auth $auth,
        private TenantContext $tenants,
        private PanelContextResolver $panels,
    ) {}

    /**
     * @param  Model|iterable<Model>|null  $recipients  null: the current user.
     */
    public function dispatch(Notification $notification, Model|iterable|null $recipients, DeliveryOptions $options): DispatchResult
    {
        $notification->assertValid();
        $this->categories->get($notification->category());

        $payload = NotificationPayload::of($notification);
        $context = $this->context($options);
        $channels = $notification->channels() ?? ($recipients === null ? ['toast'] : $this->config->defaultChannels);
        $id = (string) Str::uuid();
        $deliveries = [];
        $current = $this->currentUser();

        if (in_array('toast', $channels, true)) {
            $deliveries[] = $this->toast($id, $payload, $recipients, $current);
        }

        $persistent = array_values(array_diff($channels, ['toast']));

        if ($persistent !== []) {
            $targets = $recipients === null ? ($current === null ? [] : [$current]) : $this->recipients->normalize($recipients);

            if ($targets === []) {
                foreach ($persistent as $channel) {
                    $deliveries[] = $this->record(new Delivery($id, null, $channel, DeliveryStatus::Skipped, 'no_recipient'));
                }
            }

            foreach ($targets as $recipient) {
                $deliveries = [...$deliveries, ...$this->deliver($payload, $context, $persistent, $recipient, $options)];
            }
        }

        return new DispatchResult($deliveries);
    }

    /**
     * A toast for the current session: send() always, sendTo() only when the current user is a recipient.
     *
     * @param  Model|iterable<Model>|null  $recipients
     */
    private function toast(string $id, NotificationPayload $payload, Model|iterable|null $recipients, ?Model $current): Delivery
    {
        $reference = $current === null ? null : Recipients::reference($current);

        if (! $this->config->toasts) {
            return $this->record(new Delivery($id, $reference, 'toast', DeliveryStatus::Skipped, 'disabled'));
        }

        if ($recipients !== null) {
            $included = false;

            foreach ($current === null ? [] : $this->recipients->normalize($recipients) as $recipient) {
                $included = $included || Recipients::reference($recipient) === $reference;
            }

            if (! $included) {
                return $this->record(new Delivery($id, $reference, 'toast', DeliveryStatus::Skipped, 'not_current_user'));
            }
        }

        $pushed = $this->toasts->push(Toast::of($id, $payload, $this->config->durationFor($payload->type, $payload->priority)));

        return $this->record(new Delivery($id, $reference, 'toast', $pushed ? DeliveryStatus::Sent : DeliveryStatus::Skipped, $pushed ? null : 'no_session'));
    }

    /**
     * @param  list<string>  $channels
     * @return list<Delivery>
     */
    private function deliver(NotificationPayload $payload, DeliveryContext $context, array $channels, Model $recipient, DeliveryOptions $options): array
    {
        $id = (string) Str::uuid();
        $reference = Recipients::reference($recipient);
        $skip = fn (string $channel, string $reason): Delivery => $this->record(new Delivery($id, $reference, $channel, DeliveryStatus::Skipped, $reason));
        $deliveries = [];
        $allowed = [];

        $denied = $context->expired() ? 'expired' : $this->authorizer->deny($recipient, $payload, $context);

        foreach ($channels as $channel) {
            $reason = $denied ?? match (true) {
                $channel === 'database' && ! $this->config->database => 'disabled',
                $channel === 'broadcast' && ! $this->config->broadcasting => 'broadcasting_disabled',
                $channel === 'mail' && ! $this->config->mail => 'disabled',
                $channel === 'mail' && ! $this->hasMailAddress($recipient) => 'no_address',
                ! $this->preferences->allows($recipient, $payload->category, $channel, $context->tenant) => 'preference',
                default => null,
            };

            if ($reason === null) {
                $allowed[] = $channel;
            } else {
                $deliveries[] = $skip($channel, $reason);
            }
        }

        if ($allowed === []) {
            return $deliveries;
        }

        $queued = $options->queue ?? ($this->config->queue || $options->delay !== null);
        $connection = $this->connections->connection();
        $deferred = ! $queued && $connection instanceof Connection && ($options->afterCommit ?? $this->config->afterCommit) && $connection->transactionLevel() > 0;

        // A deferred send claims its key when the commit happens, so a rolled-back attempt does not use it up.
        if (! $deferred && $context->idempotencyKey !== null && ! $this->claim($reference, $context)) {
            return [...$deliveries, ...array_map(static fn (string $channel): Delivery => $skip($channel, 'duplicate'), $allowed)];
        }

        $class = $queued ? QueuedDeliveredNotification::class : DeliveredNotification::class;
        $laravel = new $class($payload->toArray(), $context->toArray(), $allowed, $this->channels->for($recipient), recheck: $queued, retryBackoff: $this->config->backoff);
        $laravel->id = $id;

        if ($this->localeOf($recipient) !== null) {
            $laravel->locale($this->localeOf($recipient));
        }

        if ($queued) {
            return [...$deliveries, ...$this->queue($laravel, $recipient, $options)];
        }

        if ($deferred && $connection instanceof Connection) {
            $connection->afterCommit(function () use ($laravel, $recipient, $allowed, $reference, $context, $id): void {
                if ($context->idempotencyKey !== null && ! $this->claim($reference, $context)) {
                    foreach ($allowed as $channel) {
                        $this->record(new Delivery($id, $reference, $channel, DeliveryStatus::Skipped, 'duplicate'));
                    }

                    return;
                }

                foreach ($allowed as $channel) {
                    $this->sendNow($laravel, $recipient, $channel, $reference);
                }
            });

            return [...$deliveries, ...array_map(fn (string $channel): Delivery => $this->record(new Delivery($id, $reference, $channel, DeliveryStatus::Pending, 'after_commit')), $allowed)];
        }

        foreach ($allowed as $channel) {
            $deliveries[] = $this->sendNow($laravel, $recipient, $channel, $reference);
        }

        return $deliveries;
    }

    /**
     * @return list<Delivery>
     */
    private function queue(DeliveredNotification $notification, Model $recipient, DeliveryOptions $options): array
    {
        $notification->tries = $this->config->tries;
        $notification->onConnection($options->connection ?? $this->config->queueConnection);
        $notification->onQueue($options->queueName ?? $this->config->queueName);

        if ($options->delay !== null) {
            $notification->delay($options->delay);
        }

        if ($options->afterCommit ?? $this->config->afterCommit) {
            $notification->afterCommit();
        }

        $reference = Recipients::reference($recipient);

        try {
            $this->notifications->send($recipient, $notification);
        } catch (Throwable $exception) {
            report($exception);

            return array_map(fn (string $channel): Delivery => $this->record(new Delivery((string) $notification->id, $reference, $channel, DeliveryStatus::Failed, self::reason($exception))), $notification->channels);
        }

        return array_map(fn (string $channel): Delivery => $this->record(new Delivery((string) $notification->id, $reference, $channel, DeliveryStatus::Queued)), $notification->channels);
    }

    private function sendNow(DeliveredNotification $notification, Model $recipient, string $channel, string $reference): Delivery
    {
        try {
            $this->notifications->sendNow($recipient, $notification, [DeliveredNotification::Channels[$channel]]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->record(new Delivery((string) $notification->id, $reference, $channel, DeliveryStatus::Failed, self::reason($exception)));
        }

        return $this->record(new Delivery((string) $notification->id, $reference, $channel, DeliveryStatus::Sent));
    }

    /**
     * Claim the idempotency key for this recipient (until the notification
     * expires, else for 30 days); false when it was already claimed.
     */
    private function claim(string $reference, DeliveryContext $context): bool
    {
        $seconds = $context->expiresAt === null ? 2_592_000 : max(60, $context->expiresAt->getTimestamp() - time());

        return $this->cache->add('gcego-notifications:once:'.hash('sha256', $reference.'|'.$context->idempotencyKey), true, $seconds);
    }

    private function context(DeliveryOptions $options): DeliveryContext
    {
        $tenant = $options->global ? null : ($options->tenant ?? $this->currentTenant());

        return new DeliveryContext(
            tenant: $tenant,
            panel: $options->panel ?? $this->panels->current()?->name(),
            idempotencyKey: $options->idempotencyKey,
            expiresAt: $options->expiresAt === null ? null : DateTimeImmutable::createFromInterface($options->expiresAt),
        );
    }

    private function currentTenant(): ?string
    {
        $tenant = $this->tenants->tenant();

        return $tenant === null ? null : (string) $tenant->key;
    }

    private function currentUser(): ?Model
    {
        $panel = $this->panels->current();
        $user = $panel !== null ? $panel->user : $this->auth->guard()->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * Laravel's mail channel silently skips recipients without an address; record that instead of "sent".
     */
    private function hasMailAddress(Model $recipient): bool
    {
        return method_exists($recipient, 'routeNotificationFor') && ! in_array($recipient->routeNotificationFor('mail'), [null, '', []], true);
    }

    private function localeOf(Model $recipient): ?string
    {
        if ($recipient instanceof HasLocalePreference) {
            return null;
        }

        $locale = $recipient->getAttribute($this->config->localeAttribute);

        return is_string($locale) && preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/', $locale) === 1 ? $locale : null;
    }

    private function record(Delivery $delivery): Delivery
    {
        $this->tracker->record($delivery);

        return $delivery;
    }

    private static function reason(Throwable $exception): string
    {
        return mb_substr(class_basename($exception).': '.$exception->getMessage(), 0, 250);
    }
}
