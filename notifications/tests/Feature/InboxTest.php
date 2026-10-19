<?php

declare(strict_types=1);

use Gcego\Panels\Exceptions\PanelAccessDeniedException;

it('serves the inbox inside each panel, behind the panel checks', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'In the inbox');

    expect(route('gcego.admin.notifications', [], false))->toBe('/admin/notifications')
        ->and(route('gcego.school.notifications', ['institution' => 1], false))->toBe('/school/1/notifications');

    $html = html(asMember($world['admin'])->get('/admin/notifications')->assertOk());

    expect($html)->toContain('data-gcego-panel="admin"')->toContain('In the inbox')->toContain('<h1');

    $denied = asMember($world['editor'])->get('/admin/notifications')->assertForbidden();
    expect($denied->exception)->toBeInstanceOf(PanelAccessDeniedException::class);
    asMember($world['editor'])->get('/staff/notifications')->assertOk();
    app('auth')->guard()->logout();
    app('auth')->forgetGuards();
    $this->get('/staff/notifications')->assertRedirect('/login');
});

it('filters and paginates on the server', function (): void {
    $this->reboot(['gcego-notifications.database.per_page' => 5]);
    $world = world();
    actAs($world['admin']);

    foreach (range(1, 7) as $number) {
        $this->travel(1)->minutes();
        stored($world['admin'], 'Item '.$number, static fn ($builder) => $builder->category($number === 7 ? 'security' : 'general'));
    }

    $html = (string) asMember($world['admin'])->get('/admin/notifications')->getContent();
    $snapshot = snapshotOf($html, 'gcego-notification-inbox');

    expect(squish($html))->toContain('Page 1 of 2')->toContain('Item 7')->not->toContain('Item 2');

    $next = livewireUpdate($snapshot, [['method' => 'nextPage', 'params' => ['page']]]);

    expect(squish((string) $next->json('components.0.effects.html')))->toContain('Item 2')->toContain('Page 2 of 2');

    $filtered = livewireUpdate($snapshot, updates: ['category' => 'security']);

    expect(squish((string) $filtered->json('components.0.effects.html')))->toContain('Item 7')->not->toContain('Item 6');
});

it('marks the selection read and unread, and deletes it', function (): void {
    $world = world();
    actAs($world['admin']);
    $ids = [stored($world['admin'], 'A'), stored($world['admin'], 'B'), stored($world['admin'], 'C')];
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/notifications')->getContent(), 'gcego-notification-inbox');

    $read = livewireUpdate($snapshot, [['method' => 'markAsRead', 'params' => []]], ['selected' => [$ids[0], $ids[1]]]);

    expect(repository()->unreadCount(viewer($world['admin'])))->toBe(1)
        ->and($read->json('components.0.effects.dispatches.0.name'))->toBe('gcego-notifications-refresh');

    livewireUpdate((string) $read->json('components.0.snapshot'), [['method' => 'markAsUnread', 'params' => []]], ['selected' => [$ids[0]]]);

    expect(repository()->unreadCount(viewer($world['admin'])))->toBe(2);

    livewireUpdate($snapshot, [['method' => 'delete', 'params' => []]], ['selected' => [$ids[2]]]);

    expect(repository()->find(viewer($world['admin']), $ids[2]))->toBeNull()
        ->and(repository()->recent(viewer($world['admin'])))->toHaveCount(2);
});

it('labels controls for keyboard and screen-reader users', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'Accessible');
    $html = html(asMember($world['admin'])->get('/admin/notifications'));

    expect($html)->toContain('aria-label="Select “Accessible”"')
        ->toContain('role="search" aria-label="Filter notifications"')
        ->toContain('<span class="sr-only">Unread, Information:</span>')
        ->toContain('Mark as read<span class="sr-only">: Accessible</span>');
});

it('can forbid deletion', function (): void {
    $this->reboot(['gcego-notifications.database.deletable' => false]);
    $world = world();
    actAs($world['admin']);
    $id = stored($world['admin']);
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/notifications')->getContent(), 'gcego-notification-inbox');
    livewireUpdate($snapshot, [['method' => 'delete', 'params' => []]], ['selected' => [$id]]);

    expect(repository()->find(viewer($world['admin']), $id))->not->toBeNull();
});
