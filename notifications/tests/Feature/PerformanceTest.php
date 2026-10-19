<?php

declare(strict_types=1);

use Gcego\Notifications\Broadcasting\NotificationBroadcastPayload;
use Gcego\Notifications\Database\NotificationQuery;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;
use Gcego\Notifications\Notification;
use Gcego\Notifications\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Insert $count stored notifications for $user directly (fast fixtures).
 */
function seedInbox(Model $user, int $count): void
{
    $rows = [];

    foreach (range(1, $count) as $number) {
        $rows[] = [
            'id' => (string) Str::uuid(),
            'type' => 'gcego.notification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => json_encode(['gcego' => 1, 'title' => 'Notification '.$number, 'type' => 'info', 'priority' => 'normal', 'category' => 'general', 'icon' => 'information-circle', 'actions' => [], 'metadata' => [], 'translate' => false, 'parameters' => []]),
            'read_at' => $number % 3 === 0 ? now() : null,
            'gcego_tenant' => '1',
            'gcego_category' => 'general',
            'created_at' => now()->subMinutes($number),
            'updated_at' => now()->subMinutes($number),
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('notifications')->insert($chunk);
    }
}

it('keeps a large inbox to bounded queries', function (): void {
    $world = world();
    seedInbox($world['admin'], 2000);
    $viewer = viewer($world['admin']);

    $countQueries = queriesDuring(static fn () => repository()->unreadCount($viewer));
    $start = hrtime(true);
    $pageQueries = queriesDuring(static fn () => repository()->page($viewer, new NotificationQuery('unread'), 10));
    $pageMs = (hrtime(true) - $start) / 1e6;

    recordPerformance([
        'notifications.unread_count.queries' => count($countQueries),
        'notifications.inbox_page.queries' => count($pageQueries),
        'notifications.inbox_page_2000.ms' => round($pageMs, 1),
    ]);

    expect(count($countQueries))->toBe(1)
        ->and(count($pageQueries))->toBe(2)
        ->and(repository()->unreadCount($viewer))->toBe(1334);
});

it('renders the bell with one query and the open center with two', function (): void {
    $world = world();
    seedInbox($world['admin'], 200);
    asMember($world['admin'])->get('/admin/overview');
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');
    $open = static function () use ($snapshot, &$response): void {
        $response = livewireUpdate($snapshot, [['method' => 'load', 'params' => []]]);
    };
    $queries = array_filter(queriesDuring($open), static fn (string $sql): bool => str_contains($sql, '"notifications"'));
    $snapshotBytes = strlen((string) $response->json('components.0.snapshot'));

    recordPerformance(['notifications.center_open.notification_queries' => count($queries), 'notifications.center.snapshot_bytes' => $snapshotBytes]);

    expect(count($queries))->toBe(2)
        ->and($snapshotBytes)->toBeLessThan(1500);
});

it('dispatches to many recipients without per-recipient preference queries beyond one each', function (): void {
    $world = world();
    $users = [];

    foreach (range(1, 200) as $number) {
        $users[] = User::query()->create(['name' => 'Member '.$number]);
    }

    $start = hrtime(true);
    $queries = queriesDuring(static function () use ($users, &$result): void {
        $result = Notification::make()->title('Announcement')->global()->database()->now()->sendTo($users);
    });
    $milliseconds = (hrtime(true) - $start) / 1e6;

    recordPerformance(['notifications.dispatch_200_recipients.ms' => round($milliseconds, 1), 'notifications.dispatch_200_recipients.queries' => count($queries)]);

    expect($result->summary())->toBe(['sent' => 200])
        ->and(count($queries))->toBeLessThanOrEqual(200 * 2)
        ->and($milliseconds)->toBeLessThan(10000);
});

it('queues many recipients quickly', function (): void {
    Queue::fake();
    $world = world();
    $users = [];

    foreach (range(1, 500) as $number) {
        $users[] = User::query()->create(['name' => 'Member '.$number]);
    }

    $start = hrtime(true);
    $result = Notification::make()->title('Queued announcement')->global()->database()->sendTo($users);
    $milliseconds = (hrtime(true) - $start) / 1e6;

    recordPerformance(['notifications.queue_500_recipients.ms' => round($milliseconds, 1), 'notifications.queue_500_recipients.per_second' => (int) round(500 / max($milliseconds / 1000, 0.001))]);

    expect($result->summary())->toBe(['queued' => 500])
        ->and(Queue::pushed(SendQueuedNotifications::class))->toHaveCount(500);
});

it('keeps broadcast and toast payloads small', function (): void {
    $payload = NotificationPayload::of(Notification::make()->title(str_repeat('T', 200))->body(str_repeat('B', 2000))->action('Open', '/admin/reports/1')->build());
    $broadcast = NotificationBroadcastPayload::make((string) Str::uuid(), $payload, new DeliveryContext('1', 'admin'));
    $bytes = strlen((string) json_encode($broadcast));

    recordPerformance(['notifications.broadcast_payload.max_bytes' => $bytes]);

    expect($bytes)->toBeLessThan(1024)
        ->and(mb_strlen((string) $broadcast['body']))->toBe(300);
});
