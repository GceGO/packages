<?php

declare(strict_types=1);

use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Channels\DatabaseChannel;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\Exceptions\NotificationDeliveryException;
use Gcego\Notifications\Laravel\QueuedDeliveredNotification;
use Gcego\Notifications\Notification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('queues one job per channel with the configured queue, retries and backoff', function (): void {
    Queue::fake();
    $world = world();
    $world['editor']->forceFill(['email' => 'editor@example.com'])->save();
    actAs($world['admin']);

    $result = Notification::make()->title('Queued')->database()->mail()->sendTo($world['editor']);
    $jobs = Queue::pushed(SendQueuedNotifications::class);

    expect(array_column(array_map(static fn ($delivery): array => $delivery->toArray(), $result->deliveries), 'status'))->toBe(['queued', 'queued'])
        ->and($jobs)->toHaveCount(2)
        ->and($jobs->map(static fn (SendQueuedNotifications $job): array => $job->channels)->all())->toEqual([[DatabaseChannel::class], ['mail']])
        ->and($jobs->first()->queue)->toBe('notifications')
        ->and($jobs->first()->tries)->toBe(3)
        ->and($jobs->first()->notification->backoff())->toBe([10, 60, 300])
        ->and($jobs->first()->notification)->toBeInstanceOf(QueuedDeliveredNotification::class)
        ->and(DatabaseNotification::query()->count())->toBe(0);
});

it('schedules delivery with a queue delay', function (): void {
    Queue::fake();
    $world = world();
    actAs($world['admin']);
    $at = now()->addDay();

    Notification::make()->title('Tomorrow')->database()->sendAt($at)->sendTo($world['editor']);

    expect(Queue::pushed(SendQueuedNotifications::class)->first()->delay?->format(DATE_ATOM))->toBe($at->format(DATE_ATOM));
});

it('re-checks expiry and preferences when a queued delivery runs', function (): void {
    Queue::fake();
    $world = world();
    actAs($world['admin']);
    Notification::make()->title('Soon stale')->database()->expiresAt(now()->addMinutes(5))->sendTo($world['editor']);
    Notification::make()->title('Unwanted later')->database()->sendTo($world['reader']);
    notifications()->preferences()->set($world['reader'], 'database', false);
    $this->travel(10)->minutes();

    foreach (Queue::pushed(SendQueuedNotifications::class) as $job) {
        $job->handle(app(ChannelManager::class));
    }

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('delivers after the transaction commits, and never for a rollback', function (): void {
    $world = world();
    actAs($world['admin']);

    DB::transaction(static function () use ($world): void {
        $result = Notification::make()->title('Committed')->database()->now()->sendTo($world['editor']);

        expect($result->deliveries[0]->status)->toBe(DeliveryStatus::Pending)
            ->and(DatabaseNotification::query()->count())->toBe(0);
    });

    expect(DatabaseNotification::query()->count())->toBe(1);

    try {
        DB::transaction(static function () use ($world): void {
            Notification::make()->title('Rolled back')->database()->now()->sendTo($world['editor']);

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('queues after commit too', function (): void {
    Queue::fake();
    $world = world();
    actAs($world['admin']);
    Notification::make()->title('After commit')->database()->sendTo($world['editor']);

    expect(Queue::pushed(SendQueuedNotifications::class)->first()->afterCommit)->toBeTrue();
});

it('delivers an idempotent notification once per recipient', function (): void {
    $world = world();
    actAs($world['admin']);
    $send = static fn () => Notification::make()->title('Invoice paid')->database()->now()->idempotencyKey('invoice.42.paid')->sendTo([$world['editor'], $world['reader']]);

    $first = $send();
    $second = $send();

    expect(array_column(array_map(static fn ($d): array => $d->toArray(), $first->deliveries), 'status'))->toBe(['sent', 'sent'])
        ->and(array_column(array_map(static fn ($d): array => $d->toArray(), $second->deliveries), 'reason'))->toBe(['duplicate', 'duplicate'])
        ->and(DatabaseNotification::query()->count())->toBe(2)
        ->and(Notification::make()->title('Repeated without key')->database()->now()->sendTo($world['editor'])->deliveries[0]->status)->toBe(DeliveryStatus::Sent);
});

it('backs idempotency with a unique index', function (): void {
    $world = world();
    actAs($world['admin']);
    Notification::make()->title('Once')->database()->now()->idempotencyKey('once')->sendTo($world['editor']);
    app('cache')->flush();

    Notification::make()->title('Once')->database()->now()->idempotencyKey('once')->sendTo($world['editor']);

    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('delivers to many recipients, each once, with per-recipient results', function (): void {
    $world = world();
    actAs($world['admin']);
    $result = Notification::make()->title('Staff meeting')->database()->now()->sendTo([$world['admin'], $world['editor'], $world['editor'], $world['reader']]);

    expect($result->deliveries)->toHaveCount(3)
        ->and(array_unique(array_map(static fn ($delivery): ?string => $delivery->recipient, $result->deliveries)))->toHaveCount(3)
        ->and(DatabaseNotification::query()->pluck('data')->map(static fn (array $data): array => array_keys($data))->unique()->values()->all()[0])->not->toContain('recipients');
});

it('refuses unbounded recipient lists and non-notifiable recipients', function (): void {
    $this->reboot(['gcego-notifications.recipients.max' => 2]);
    $world = world();

    expect(fn () => Notification::make()->title('x')->database()->sendTo([$world['admin'], $world['editor'], $world['reader']]))->toThrow(InvalidNotificationException::class, 'at most 2 recipients')
        ->and(fn () => Notification::make()->title('x')->database()->sendTo($world['subjects'][0]))->toThrow(InvalidNotificationException::class, 'Notifiable');
});

it('mails through Laravel, in the recipient\'s locale', function (): void {
    $world = world();
    $world['editor']->forceFill(['email' => 'editor@example.com', 'locale' => 'fr'])->save();
    actAs($world['admin']);

    $result = Notification::make()->title('gcego-notifications::messages.done')->translate()->body('Body')->action('Open', '/admin/reports')->mail()->now()->sendTo($world['editor']);
    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($result->deliveries[0]->status)->toBe(DeliveryStatus::Sent)
        ->and($messages)->toHaveCount(1)
        ->and($messages[0]->getOriginalMessage()->getSubject())->toBe('Terminé.')
        ->and($messages[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe('editor@example.com')
        ->and($messages[0]->toString())->toContain('http://localhost/admin/reports');
});

it('records failures instead of discarding them', function (): void {
    $this->reboot(['mail.default' => 'missing']);
    $world = world();
    $world['editor']->forceFill(['email' => 'editor@example.com'])->save();
    actAs($world['admin']);

    $result = Notification::make()->title('Will fail')->mail()->database()->now()->sendTo($world['editor']);

    expect($result->successful())->toBeFalse()
        ->and($result->channel('mail')[0]->status)->toBe(DeliveryStatus::Failed)
        ->and($result->channel('mail')[0]->reason)->toContain('missing')
        ->and($result->channel('database')[0]->status)->toBe(DeliveryStatus::Sent)
        ->and(fn () => $result->throw())->toThrow(NotificationDeliveryException::class, '1 notification deliveries failed');
});

it('skips mail for recipients without an address instead of reporting it sent', function (): void {
    $world = world();
    actAs($world['admin']);

    expect(Notification::make()->title('No address')->mail()->now()->sendTo($world['editor'])->deliveries[0]->reason)->toBe('no_address')
        ->and(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('broadcasts a minimal payload on the recipient\'s private channel', function (): void {
    $this->reboot(['gcego-notifications.broadcasting.enabled' => true, 'broadcasting.default' => 'log']);
    Event::fake([BroadcastNotificationCreated::class]);
    $world = world();
    actAs($world['admin']);

    $result = Notification::make()->title('New Message')->body('You have received a message.')->metadata(['student' => 'Jane Doe'])->broadcast()->now()->sendTo($world['editor']);

    Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($world, $result): bool {
        $payload = $event->broadcastWith();
        $channel = $event->broadcastOn()[0]->name;

        return $channel === 'private-'.app(NotificationChannels::class)->for($world['editor'])
            && preg_match('/^private-gcego\.notifications\.[a-f0-9]{40}$/', $channel) === 1
            && $payload['notification_type'] === 'info'
            && $event->broadcastType() === 'gcego.notification'
            && $payload['id'] === $result->deliveries[0]->notification
            && $payload['title'] === 'New Message'
            && $payload['tenant'] === '1'
            && ! array_key_exists('metadata', $payload)
            && ! str_contains((string) json_encode($payload), 'Jane Doe')
            && strlen((string) json_encode($payload)) < 1024;
    });
});

it('skips broadcasting until it is enabled, while the inbox keeps working', function (): void {
    $world = world();
    actAs($world['admin']);
    $result = Notification::make()->title('Offline')->database()->broadcast()->now()->sendTo($world['editor']);

    expect($result->channel('broadcast')[0]->reason)->toBe('broadcasting_disabled')
        ->and($result->channel('database')[0]->status)->toBe(DeliveryStatus::Sent)
        ->and(repository()->unreadCount(viewer($world['editor'])))->toBe(1);
});

it('tracks queued, sent, failed and skipped deliveries when enabled', function (): void {
    $this->reboot(['gcego-notifications.tracking.enabled' => true, 'gcego-notifications.queue.connection' => 'sync']);
    $world = world();
    actAs($world['admin']);
    notifications()->preferences()->set($world['reader'], 'database', false);

    $result = Notification::make()->title('Tracked')->database()->sendTo([$world['editor'], $world['reader']]);
    $rows = DB::table('gcego_notification_deliveries')->orderBy('id')->get(['notification_id', 'channel', 'status', 'reason'])->map(static fn ($row): array => (array) $row)->all();

    expect(array_column($rows, 'status'))->toBe(['sent', 'queued', 'skipped'])
        ->and($rows[0]['notification_id'])->toBe($result->deliveries[0]->notification)
        ->and($rows[2]['reason'])->toBe('preference');

    (new QueuedDeliveredNotification([], ['tenant' => null, 'panel' => null, 'key' => null, 'expires_at' => null], ['mail'], 'x'))->forceFill($result->deliveries[0]->notification)->failed(new RuntimeException('SMTP down'));

    expect(DB::table('gcego_notification_deliveries')->where('status', 'failed')->value('reason'))->toBe('RuntimeException: SMTP down');
});
