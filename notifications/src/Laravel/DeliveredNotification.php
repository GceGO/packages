<?php

declare(strict_types=1);

namespace Gcego\Notifications\Laravel;

use Gcego\Notifications\Broadcasting\NotificationBroadcastPayload;
use Gcego\Notifications\Channels\DatabaseChannel;
use Gcego\Notifications\Contracts\NotificationDeliveryTracker;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;
use Gcego\Notifications\Delivery\RecipientAuthorizer;
use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\Dispatching\Delivery;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Preferences\NotificationPreferenceManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;
use Throwable;

/**
 * The Laravel notification gcego/notifications hands to Laravel's
 * ChannelManager, one per recipient: Laravel stores it (database channel),
 * broadcasts it (Reverb), mails it and queues it. It carries only the
 * normalized payload and the delivery context: scalar data, safe to
 * serialize into queue jobs.
 *
 * Queued deliveries are checked again when the job runs (shouldSend):
 * expiry, tenant membership, the delivery policy and preferences.
 */
class DeliveredNotification extends LaravelNotification
{
    use Queueable;

    public const array Channels = ['database' => DatabaseChannel::class, 'broadcast' => 'broadcast', 'mail' => 'mail'];

    public ?int $tries = null;

    /** The channel of this copy (Laravel clones a queued notification per channel). */
    public ?string $deliveryChannel = null;

    /**
     * @param  array<string, mixed>  $payload  NotificationPayload::toArray()
     * @param  array{tenant: string|null, panel: string|null, key: string|null, expires_at: string|null}  $context
     * @param  list<string>  $channels  database, broadcast and/or mail
     * @param  list<int>  $retryBackoff
     */
    public function __construct(
        public array $payload,
        public array $context,
        public array $channels,
        public string $broadcastChannel,
        public bool $recheck = false,
        public array $retryBackoff = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_map(static fn (string $channel): string => self::Channels[$channel], array_filter($this->channels, static fn (string $channel): bool => isset(self::Channels[$channel]))));
    }

    /**
     * Per queued copy: remember its channel (for failure tracking).
     *
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        $this->deliveryChannel = self::channelName($channel);

        return [];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->retryBackoff;
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $this->recheck || ! $notifiable instanceof Model) {
            return true;
        }

        $payload = $this->notificationPayload();
        $context = DeliveryContext::fromArray($this->context);
        $name = self::channelName($channel);
        $reason = match (true) {
            $context->expired() => 'expired',
            default => app(RecipientAuthorizer::class)->deny($notifiable, $payload, $context)
                ?? (app(NotificationPreferenceManager::class)->allows($notifiable, $payload->category, $name, $context->tenant) ? null : 'preference'),
        };

        if ($reason !== null) {
            app(NotificationDeliveryTracker::class)->record(new Delivery((string) $this->id, Recipients::reference($notifiable), $name, DeliveryStatus::Skipped, $reason));
        }

        return $reason === null;
    }

    public const string DatabaseType = 'gcego.notification';

    public function databaseType(object $notifiable): string
    {
        return self::DatabaseType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(NotificationBroadcastPayload::make((string) $this->id, $this->notificationPayload(), DeliveryContext::fromArray($this->context)));
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->broadcastChannel)];
    }

    public function broadcastType(): string
    {
        return 'gcego.notification';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->notificationPayload();
        $mail = (new MailMessage)->subject($payload->displayTitle());

        if ($payload->displayBody() !== null) {
            $mail->line((string) $payload->displayBody());
        }

        foreach ($payload->actions as $action) {
            $mail->action($action['label'], url($action['url']));

            break;
        }

        return $payload->type->value === 'error' ? $mail->error() : $mail;
    }

    /**
     * A queued delivery failed for good (after its retries).
     */
    public function failed(Throwable $exception): void
    {
        app(NotificationDeliveryTracker::class)->record(new Delivery((string) $this->id, null, $this->deliveryChannel ?? 'queue', DeliveryStatus::Failed, mb_substr(class_basename($exception).': '.$exception->getMessage(), 0, 250)));
    }

    public function notificationPayload(): NotificationPayload
    {
        return NotificationPayload::fromArray($this->payload) ?? throw new \UnexpectedValueException('The notification payload is not a gcego payload.');
    }

    /**
     * Give this delivery its notification id (Laravel sets it when sending; tests and tools may set it explicitly).
     */
    public function forceFill(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public static function channelName(string $channel): string
    {
        return array_search($channel, self::Channels, true) ?: $channel;
    }
}
