<?php

declare(strict_types=1);

use Gcego\Notifications\Broadcasting\NotificationChannelAuthorizer;
use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Notification;
use Gcego\Notifications\Tests\Fixtures\Policies\NoReadersPolicy;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('never lets a user read, change or delete another user\'s notification', function (): void {
    $world = world();
    actAs($world['admin']);
    $theirs = stored($world['editor'], 'Private to the editor');
    $mine = viewer($world['reader']);

    expect(repository()->find($mine, $theirs))->toBeNull()
        ->and(repository()->markRead($mine, [$theirs]))->toBe(0)
        ->and(repository()->markUnread($mine, [$theirs]))->toBe(0)
        ->and(repository()->delete($mine, [$theirs]))->toBe(0)
        ->and(repository()->markAllRead($mine))->toBe(0)
        ->and(repository()->find(viewer($world['editor']), $theirs)?->read)->toBeFalse();
});

it('ignores forged ids sent to the center and the inbox', function (): void {
    $world = world();
    actAs($world['admin']);
    $theirs = stored($world['editor'], 'Private to the editor', static fn ($builder) => $builder->action('Secret', '/admin/secret'));
    $center = snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center');
    $inbox = snapshotOf((string) asMember($world['admin'])->get('/admin/notifications')->getContent(), 'gcego-notification-inbox');

    $opened = livewireUpdate($center, [['method' => 'open', 'params' => [$theirs, 0]]]);
    livewireUpdate($center, [['method' => 'markAsRead', 'params' => [$theirs]]]);
    livewireUpdate($center, [['method' => 'markAsRead', 'params' => ["' OR 1=1 --"]]]);
    livewireUpdate($inbox, [['method' => 'delete', 'params' => []]], ['selected' => [$theirs, ['nested'], 42]]);

    expect($opened->json('components.0.effects.redirect'))->toBeNull()
        ->and((string) $opened->json('components.0.effects.html'))->not->toContain('Private to the editor')
        ->and(repository()->find(viewer($world['editor']), $theirs)?->read)->toBeFalse();
});

it('keeps notifications inside their tenant', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'Institution 1 only');

    $html = (string) livewireUpdate(snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center'), [['method' => 'load', 'params' => []]])->json('components.0.effects.html');

    expect($html)->toContain('Institution 1 only')
        ->and(repository()->recent(viewer($world['admin'], '2')))->toBe([]);
});

it('delivers tenant notifications only to members of the tenant', function (): void {
    $world = world();
    actAs($world['admin']);

    $result = Notification::make()->title('Institution 1 news')->database()->now()->sendTo([$world['editor'], $world['south']]);

    expect(array_column(array_map(static fn ($d): array => $d->toArray(), $result->deliveries), 'reason'))->toBe([null, 'tenant'])
        ->and(repository()->recent(viewer($world['south'], '2')))->toBe([])
        ->and(Notification::make()->title('To institution 2')->tenant('2')->database()->now()->sendTo($world['south'])->deliveries[0]->reason)->toBeNull();
});

it('applies the application\'s delivery policy, even for trusted senders', function (): void {
    $this->reboot(['gcego-notifications.security.delivery_policy' => NoReadersPolicy::class]);
    $world = world();
    actAs($world['admin']);

    $result = Notification::make()->title('Maintenance')->category('system')->critical()->database()->now()->sendTo([$world['editor'], $world['reader']]);

    expect(array_column(array_map(static fn ($d): array => $d->toArray(), $result->deliveries), 'reason'))->toBe([null, 'policy']);
});

it('authorizes private channel subscriptions for their owner only', function (): void {
    $world = world();
    $channels = app(NotificationChannels::class);
    $authorizer = app(NotificationChannelAuthorizer::class);
    $hash = $channels->hash($world['editor']);

    expect($authorizer->join($world['editor'], $hash))->toBeTrue()
        ->and($authorizer->join($world['reader'], $hash))->toBeFalse()
        ->and($authorizer->join(null, $hash))->toBeFalse()
        ->and($authorizer->join($world['editor'], (string) $world['editor']->id))->toBeFalse()
        ->and($channels->for($world['editor']))->not->toContain((string) $world['editor']->getKey().'.')
        ->and($channels->hash($world['editor']))->not->toBe($channels->hash($world['reader']));
});

it('registers the private channel only when broadcasting is enabled', function (): void {
    $pattern = app(NotificationChannels::class)->pattern();

    expect(app(BroadcastManager::class)->connection()->getChannels()->has($pattern))->toBeFalse();

    $this->reboot(['gcego-notifications.broadcasting.enabled' => true, 'broadcasting.default' => 'log']);
    $world = world();
    $broadcaster = app(BroadcastManager::class)->connection();
    $channel = app(NotificationChannels::class)->for($world['editor']);
    // Laravel's own channel authorization (what the Reverb and Pusher broadcasters run for /broadcasting/auth).
    $verify = Closure::bind(fn (Request $request, string $channel): mixed => $this->verifyUserCanAccessChannel($request, $channel), $broadcaster, $broadcaster::class);
    $as = static function (?Model $user): Request {
        $request = Request::create('/broadcasting/auth', 'POST');
        $request->setUserResolver(static fn () => $user);

        return $request;
    };

    expect($broadcaster->getChannels()->has($pattern))->toBeTrue()
        ->and(fn () => $verify($as($world['reader']), $channel))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $verify($as($world['editor']), 'gcego.notifications.'.str_repeat('0', 40)))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $verify($as($world['editor']), $channel))->not->toThrow(Throwable::class);
});

it('escapes notification text everywhere it is shown', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], '<script>alert("title")</script>', static fn ($builder) => $builder->body('<img src=x onerror=alert(1)>'));
    $center = (string) livewireUpdate(snapshotOf((string) asMember($world['admin'])->get('/admin/overview')->getContent(), 'gcego-notification-center'), [['method' => 'load', 'params' => []]])->json('components.0.effects.html');
    $inbox = (string) asMember($world['admin'])->get('/admin/notifications')->getContent();

    foreach ([$center, $inbox] as $html) {
        expect($html)->not->toContain('<script>alert("title")</script>')
            ->not->toContain('<img src=x onerror=alert(1)>')
            ->toContain('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;');
    }
});

it('refuses unsafe links and keeps destination authorization', function (): void {
    $world = world();
    actAs($world['admin']);

    expect(fn () => Notification::make()->title('x')->action('Go', 'javascript:alert(1)')->database()->sendTo($world['editor']))->toThrow(Exception::class, 'root-relative');

    stored($world['editor'], 'Admin report', static fn ($builder) => $builder->action('Open', '/admin/reports'));
    asMember($world['editor'])->get('/admin/reports')->assertForbidden();
});

it('never serializes models or the viewer into Livewire snapshots or payloads', function (): void {
    $world = world();
    $world['admin']->forceFill(['email' => 'admin-secret@example.com'])->save();
    actAs($world['admin']);
    stored($world['admin'], 'Hello');
    $html = (string) asMember($world['admin'])->get('/admin/notifications')->getContent();

    foreach (['gcego-notification-center', 'gcego-notification-inbox', 'gcego-toasts'] as $component) {
        expect(snapshotOf($html, $component))->not->toBe('')
            ->not->toContain('admin-secret@example.com')
            ->not->toContain('Fixtures\\\\Models\\\\User');
    }

    expect(app(ViewerResolver::class)->current())->not->toBeNull();
});

it('shows nothing to guests', function (): void {
    world();

    expect(notifications()->unreadCount())->toBe(0)
        ->and(notifications()->markAllAsRead())->toBe(0)
        ->and(notifications()->inbox())->toBeNull();
});
