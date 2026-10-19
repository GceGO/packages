<?php

declare(strict_types=1);

it('shows the bell with the unread count in its accessible name', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'One');
    stored($world['admin'], 'Two');
    $html = html(asMember($world['admin'])->get('/admin/overview'));

    expect($html)->toContain('aria-label="Notifications, 2 unread"')
        ->toMatch('/<span aria-hidden="true" class="[^"]*rounded-full[^"]*">2<\/span>/')
        ->toContain('aria-expanded="false"')
        ->toContain('Loading notifications…')
        ->not->toContain('One');
});

it('loads the list only when it is opened', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'Report ready', static fn ($builder) => $builder->body('Your requested report is available.')->action('View report', '/admin/reports'));
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');
    $html = squish((string) livewireUpdate($snapshot, [['method' => 'load', 'params' => []]])->json('components.0.effects.html'));

    expect($html)->toContain('Report ready')
        ->toContain('Your requested report is available.')
        ->toContain('<a href="/admin/reports" wire:click.prevent="open(')
        ->toContain('Mark all as read')
        ->toContain('<a href="/admin/notifications"')
        ->toMatch('/<time datetime="[^"]+" title="[^"]+">\d+ seconds? ago<\/time>|<time datetime="[^"]+" title="[^"]+">1 second ago<\/time>|<time datetime="[^"]+" title="[^"]+">0 seconds ago<\/time>/');
});

it('marks one or all notifications read from the center', function (): void {
    $world = world();
    actAs($world['admin']);
    $first = stored($world['admin'], 'First');
    stored($world['admin'], 'Second');
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');

    $response = livewireUpdate($snapshot, [['method' => 'markAsRead', 'params' => [$first]]]);

    expect(repository()->find(viewer($world['admin']), $first)?->read)->toBeTrue()
        ->and(squish((string) $response->json('components.0.effects.html')))->toContain('aria-label="Notifications, 1 unread"');

    $response = livewireUpdate((string) $response->json('components.0.snapshot'), [['method' => 'markAllAsRead', 'params' => []]]);

    expect(repository()->unreadCount(viewer($world['admin'])))->toBe(0)
        ->and(squish((string) $response->json('components.0.effects.html')))->toContain('aria-label="Notifications"');
});

it('opens a notification\'s link through the server, marking it read', function (): void {
    $world = world();
    actAs($world['admin']);
    $id = stored($world['admin'], 'Report', static fn ($builder) => $builder->action('View', '/admin/reports'));
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');
    $response = livewireUpdate($snapshot, [['method' => 'open', 'params' => [$id, 0]]]);

    expect($response->json('components.0.effects.redirect'))->toBe('/admin/reports')
        ->and(repository()->find(viewer($world['admin']), $id)?->read)->toBeTrue();
});

it('shows an empty state', function (): void {
    $world = world();
    $snapshot = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');

    expect((string) livewireUpdate($snapshot, [['method' => 'load', 'params' => []]])->json('components.0.effects.html'))->toContain('You have no notifications.');
});

it('refreshes on broadcast and refresh events, and polls only when configured', function (): void {
    $world = world();
    $html = squish((string) asMember($world['admin'])->get('/admin/overview')->getContent());

    expect($html)->toContain('x-on:gcego-notification-received.window="$wire.$refresh()"')
        ->not->toContain('wire:poll');

    $this->reboot(['gcego-notifications.broadcasting.poll_seconds' => 30]);
    $world = world();

    expect(html(asMember($world['admin'])->get('/admin/overview')))->toContain('wire:poll.30s.visible="refresh"');
});
