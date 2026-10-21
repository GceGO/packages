<?php

declare(strict_types=1);

use Gcego\Actions\Execution\ActionResult;
use Gcego\Core\Packages\PackageManager;
use Gcego\Notifications\Definitions\NotificationType;
use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\Integration\PanelNotificationsExtension;
use Gcego\Notifications\Notification;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\Extensions\PanelExtensions;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;

it('adds the bell and toasts to every panel layout', function (string $user, string $uri, string $panel): void {
    $world = world();
    $html = html(asMember($world[$user])->get($uri)->assertOk());

    expect(substr_count($html, 'wire:name="gcego-notification-center"'))->toBe(1)
        ->and(substr_count($html, 'x-data="gcegoToasts('))->toBe(1)
        ->and($html)->toContain('href="'.route('gcego.'.$panel.'.notifications', $panel === 'school' ? ['institution' => 1] : [], false).'"');
})->with([['admin', '/admin/overview', 'admin'], ['reader', '/school/1/subjects', 'school'], ['editor', '/staff', 'staff']]);

it('shows a user\'s notifications in every panel they may enter', function (): void {
    $world = world();
    actAs($world['admin']);
    // Global: the staff panel has no tenant, and tenant notifications only show in their tenant.
    stored($world['admin'], 'Shared across panels', static fn ($builder) => $builder->global());

    expect(html(asMember($world['admin'])->get('/admin/notifications')))->toContain('Shared across panels')
        ->and(html(asMember($world['admin'])->get('/staff/notifications')))->toContain('Shared across panels');
});

it('filters notifications per panel when configured', function (): void {
    $this->reboot(['gcego-notifications.panels.filter' => true]);
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'For admin', static fn ($builder) => $builder->panel('admin')->global());
    stored($world['admin'], 'For staff', static fn ($builder) => $builder->panel('staff')->global());

    expect(html(asMember($world['admin'])->get('/admin/notifications')))->toContain('For admin')->not->toContain('For staff')
        ->and(html(asMember($world['admin'])->get('/staff/notifications')))->toContain('For staff')->not->toContain('For admin');
});

it('hides tenant notifications in panels without that tenant', function (): void {
    $world = world();
    actAs($world['admin']);
    stored($world['admin'], 'Institution 1 only');

    expect(html(asMember($world['admin'])->get('/admin/notifications')))->toContain('Institution 1 only')
        ->and(html(asMember($world['admin'])->get('/staff/notifications')))->not->toContain('Institution 1 only');
});

it('records the current panel as the notification\'s panel', function (): void {
    $world = world();
    actAs($world['admin']);
    app(PanelContextResolver::class)->set(new PanelContext(panels()->descriptor('staff'), $world['admin'], 'web', null, 'gcego.staff.'));
    stored($world['editor'], 'From the staff room');

    expect(DatabaseNotification::query()->value('gcego_panel'))->toBe('staff')
        ->and(app(PanelExtensions::class)->classes())->toContain(PanelNotificationsExtension::class);
});

it('links to pages of a panel', function (): void {
    $world = world();
    actAs($world['admin']);
    $id = stored($world['editor'], 'Subject updated', static fn ($builder) => $builder->actionToPage('View subject', 'academics.subjects.view', $world['subjects'][0]->id, 'admin'));

    expect(repository()->find(viewer($world['editor']), $id)?->payload->actions[0]['url'])->toBe('/admin/subjects/'.$world['subjects'][0]->id)
        ->and(fn () => Notification::make()->title('x')->actionToPage('Nowhere', 'missing', panel: 'admin'))->toThrow(InvalidNotificationException::class, 'has no route');
});

it('turns action results into notifications of the matching type', function (string $factory, NotificationType $type): void {
    $result = match ($factory) {
        'success' => ActionResult::success('subjects.create', 'Student record updated successfully.'),
        'failed' => ActionResult::failed('subjects.create', 'The record could not be saved.'),
        'denied' => ActionResult::denied('subjects.create', 'You may not do that.'),
        'conflict' => ActionResult::conflict('subjects.create', 'Someone else changed it.'),
    };
    $notification = Notification::fromActionResult($result)->build();

    expect($notification->notificationType())->toBe($type)
        ->and($notification->title())->toBe($result->message);
})->with([['success', NotificationType::Success], ['failed', NotificationType::Error], ['denied', NotificationType::Warning], ['conflict', NotificationType::Error]]);

it('shows action-host results as toasts without sending persistent notifications', function (): void {
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/toasted')->getContent();

    expect($html)->toContain('x-on:gcego-action-completed.window="fromAction($event.detail.result)"')
        ->and(toastState($html)['actions'])->toBeTrue()
        ->and(DatabaseNotification::query()->count())->toBe(0);
});

it('integrates with the GCEGO packages it builds on', function (): void {
    $descriptor = app(PackageManager::class)->descriptor('gcego/notifications');
    $installed = array_map(static fn ($package): string => $package->id, app(PackageManager::class)->descriptors());

    expect($descriptor->requires)->toContain('gcego/panels', 'gcego/security', 'gcego/actions', 'gcego/chroma')
        ->and($descriptor->capabilities)->toContain('notifications.toasts', 'notifications.broadcast')
        ->and(array_intersect(['gcego/morfaw', 'gcego/core', 'gcego/blueprints', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/widgets', 'gcego/layouts', 'gcego/navigation', 'gcego/pages', 'gcego/modules', 'gcego/panels', 'gcego/notifications'], $installed))->not->toBeEmpty()
        ->and(Livewire::exists('gcego-toasts'))->toBeTrue()
        ->and(Livewire::exists('gcego-notification-center'))->toBeTrue()
        ->and(Livewire::exists('gcego-notification-inbox'))->toBeTrue()
        ->and(Livewire::exists('gcego-notification-preferences'))->toBeTrue();
});

it('keeps its configuration cacheable', function (): void {
    $config = config('gcego-notifications');

    expect(eval('return '.var_export($config, true).';'))->toBe($config);
});
