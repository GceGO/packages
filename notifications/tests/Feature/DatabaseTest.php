<?php

declare(strict_types=1);

use Gcego\Notifications\Database\NotificationQuery;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

it('persists notifications for their recipient in Laravel\'s notifications table', function (): void {
    $world = world();
    actAs($world['admin']);

    $result = Notification::make()->title('New Institution Request')->body('An institution registration requires review.')->category('system')->action('Review', '/admin/requests/7')->database()->now()->sendTo($world['editor']);
    $row = DatabaseNotification::query()->sole();

    expect($result->deliveries[0]->status)->toBe(DeliveryStatus::Sent)
        ->and($row->getAttribute('notifiable_id'))->toBe($world['editor']->id)
        ->and($row->getAttribute('type'))->toBe('gcego.notification')
        ->and($row->getAttribute('gcego_tenant'))->toBe('1')
        ->and($row->getAttribute('gcego_category'))->toBe('system')
        ->and($row->getAttribute('data'))->toMatchArray(['title' => 'New Institution Request', 'actions' => [['label' => 'Review', 'url' => '/admin/requests/7', 'primary' => false, 'new_tab' => false]]])
        ->and($world['editor']->unreadNotifications()->count())->toBe(1);
});

it('lists, counts and paginates only the viewer\'s notifications, newest first', function (): void {
    $world = world();
    actAs($world['admin']);

    foreach (range(1, 25) as $number) {
        $this->travel(1)->minutes();
        stored($world['editor'], 'Editor '.$number);
    }

    stored($world['reader'], 'Reader only');
    $page = repository()->page(viewer($world['editor']), new NotificationQuery, 1, 10);

    expect($page->total())->toBe(25)
        ->and($page->lastPage())->toBe(3)
        ->and($page->items()[0]?->payload->title)->toBe('Editor 25')
        ->and(repository()->page(viewer($world['editor']), new NotificationQuery, 3, 10)->items())->toHaveCount(5)
        ->and(repository()->unreadCount(viewer($world['editor'])))->toBe(25)
        ->and(repository()->unreadCount(viewer($world['reader'])))->toBe(1)
        ->and(array_map(static fn ($item): string => $item->payload->title, repository()->recent(viewer($world['editor']), 3)))->toBe(['Editor 25', 'Editor 24', 'Editor 23']);
});

it('marks notifications read and unread, idempotently', function (): void {
    $world = world();
    actAs($world['admin']);
    $id = stored($world['editor']);
    $viewer = viewer($world['editor']);

    expect(repository()->markRead($viewer, [$id]))->toBe(1)
        ->and(repository()->markRead($viewer, [$id]))->toBe(0)
        ->and(repository()->find($viewer, $id)?->read)->toBeTrue()
        ->and(repository()->unreadCount($viewer))->toBe(0)
        ->and(repository()->markUnread($viewer, [$id]))->toBe(1)
        ->and(repository()->find($viewer, $id)?->read)->toBeFalse();
});

it('marks all as read and deletes', function (): void {
    $world = world();
    actAs($world['admin']);
    $ids = [stored($world['editor'], 'A'), stored($world['editor'], 'B'), stored($world['editor'], 'C')];
    $viewer = viewer($world['editor']);

    expect(repository()->markAllRead($viewer))->toBe(3)
        ->and(repository()->unreadCount($viewer))->toBe(0)
        ->and(repository()->delete($viewer, [$ids[0], $ids[1]]))->toBe(2)
        ->and(repository()->page($viewer, new NotificationQuery)->total())->toBe(1);
});

it('filters by status, category and dates', function (): void {
    $world = world();
    actAs($world['admin']);
    $this->travelTo('2026-10-01 09:00');
    $old = stored($world['editor'], 'Old system', static fn ($builder) => $builder->category('system'));
    $this->travelTo('2026-10-10 09:00');
    stored($world['editor'], 'New account', static fn ($builder) => $builder->category('account'));
    $viewer = viewer($world['editor']);
    repository()->markRead($viewer, [$old]);
    $titles = static fn (NotificationQuery $query): array => array_map(static fn ($item): string => $item->payload->title, array_values(array_filter(repository()->page($viewer, $query)->items())));

    expect($titles(new NotificationQuery('unread')))->toBe(['New account'])
        ->and($titles(new NotificationQuery('read')))->toBe(['Old system'])
        ->and($titles(NotificationQuery::fromInput('all', 'system')))->toBe(['Old system'])
        ->and($titles(NotificationQuery::fromInput('all', null, '2026-10-05')))->toBe(['New account'])
        ->and($titles(NotificationQuery::fromInput('all', null, null, '2026-10-05')))->toBe(['Old system'])
        ->and($titles(NotificationQuery::fromInput('everything', 'Bad Category!', 'yesterday', '<script>')))->toBe(['New account', 'Old system']);
});

it('hides expired notifications', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['editor'], 'Short-lived', static fn ($builder) => $builder->expiresAt(now()->addHour()));

    expect(repository()->unreadCount(viewer($world['editor'])))->toBe(1);

    $this->travel(2)->hours();

    expect(repository()->unreadCount(viewer($world['editor'])))->toBe(0)
        ->and(repository()->recent(viewer($world['editor'])))->toBe([]);
});

it('shows each tenant only its own and tenant-less notifications', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'Institution 1');
    $this->travel(1)->minutes();
    stored($world['admin'], 'Everywhere', static fn ($builder) => $builder->global());
    $titles = static fn (?string $tenant): array => array_map(static fn ($item): string => $item->payload->title, repository()->recent(viewer($world['admin'], $tenant)));

    expect($titles('1'))->toBe(['Everywhere', 'Institution 1'])
        ->and($titles('2'))->toBe(['Everywhere'])
        ->and($titles(null))->toBe(['Everywhere']);
});

it('caches unread counts per user, tenant and panel, and refreshes them on change', function (): void {
    $this->reboot(['gcego-notifications.database.unread_cache_ttl' => 60]);
    $world = world();
    actAs($world['admin']);
    $id = stored($world['editor']);
    $viewer = viewer($world['editor']);

    expect(repository()->unreadCount($viewer))->toBe(1)
        ->and(queriesDuring(static fn () => repository()->unreadCount($viewer)))->toBe([])
        ->and(repository()->unreadCount(viewer($world['reader'])))->toBe(0);

    repository()->markRead($viewer, [$id]);

    expect(repository()->unreadCount($viewer))->toBe(0);

    stored($world['editor'], 'Another');

    expect(repository()->unreadCount($viewer))->toBe(1);
});

it('leaves other notifications in the table alone', function (): void {
    $world = world();
    DatabaseNotification::query()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\Legacy', 'notifiable_type' => $world['editor']->getMorphClass(), 'notifiable_id' => $world['editor']->id, 'data' => ['message' => 'legacy']]);
    actAs($world['admin']);
    stored($world['editor'], 'Ours');

    expect(array_map(static fn ($item): string => $item->payload->title, repository()->recent(viewer($world['editor']))))->toBe(['Ours'])
        ->and(repository()->markAllRead(viewer($world['editor'])))->toBe(1)
        ->and(DatabaseNotification::query()->whereNull('read_at')->count())->toBe(1);
});
