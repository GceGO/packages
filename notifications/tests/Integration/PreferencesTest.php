<?php

declare(strict_types=1);

use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\Livewire\NotificationPreferences;
use Gcego\Notifications\Notification;
use Illuminate\Support\Facades\DB;

it('delivers on every channel by default', function (): void {
    $world = world();
    $preferences = notifications()->preferences();

    expect($preferences->allows($world['editor'], 'general', 'database'))->toBeTrue()
        ->and($preferences->allows($world['editor'], 'general', 'mail'))->toBeTrue()
        ->and($preferences->allows($world['editor'], 'general', 'toast'))->toBeTrue();
});

it('applies the most specific preference: tenant and category, tenant, category, all', function (): void {
    $world = world();
    $preferences = notifications()->preferences();
    $user = $world['editor'];

    $preferences->set($user, 'mail', false);
    expect($preferences->allows($user, 'system', 'mail', '1'))->toBeFalse();

    $preferences->set($user, 'mail', true, 'system');
    expect($preferences->allows($user, 'system', 'mail', '1'))->toBeTrue()
        ->and($preferences->allows($user, 'account', 'mail', '1'))->toBeFalse();

    $preferences->set($user, 'mail', false, tenant: '1');
    expect($preferences->allows($user, 'system', 'mail', '1'))->toBeFalse()
        ->and($preferences->allows($user, 'system', 'mail', '2'))->toBeTrue();

    $preferences->set($user, 'mail', true, 'system', '1');
    expect($preferences->allows($user, 'system', 'mail', '1'))->toBeTrue()
        ->and($preferences->allows($world['reader'], 'system', 'mail', '1'))->toBeTrue();
});

it('skips channels the recipient turned off, per recipient', function (): void {
    $world = world();
    actAs($world['admin']);
    notifications()->preferences()->set($world['reader'], 'database', false);

    $result = Notification::make()->title('Weekly digest')->database()->now()->sendTo([$world['editor'], $world['reader']]);

    expect(array_column(array_map(static fn ($d): array => $d->toArray(), $result->deliveries), 'reason'))->toBe([null, 'preference']);
});

it('always delivers mandatory channels of a category', function (): void {
    $this->reboot(['gcego-notifications.categories' => ['alerts' => ['label' => 'Security alerts', 'mandatory' => ['database']]]]);
    $world = world();
    actAs($world['admin']);
    $preferences = notifications()->preferences();
    $preferences->set($world['editor'], 'database', false);

    $result = Notification::make()->title('New sign-in')->category('alerts')->database()->now()->sendTo($world['editor']);

    expect($result->deliveries[0]->reason)->toBeNull()
        ->and($preferences->changeable('alerts', 'database'))->toBeFalse()
        ->and($preferences->changeable('alerts', 'mail'))->toBeTrue();
});

it('uses configured defaults, and only defaults when preferences are disabled', function (): void {
    $this->reboot(['gcego-notifications.preferences.defaults' => ['database' => true, 'broadcast' => true, 'mail' => false]]);
    $world = world();

    expect(notifications()->preferences()->allows($world['editor'], 'general', 'mail'))->toBeFalse();

    $this->reboot(['gcego-notifications.preferences.enabled' => false]);
    $world = world();
    notifications()->preferences()->set($world['editor'], 'database', false);

    expect(notifications()->preferences()->allows($world['editor'], 'general', 'database'))->toBeTrue();
});

it('refuses unknown channels and categories', function (): void {
    $world = world();

    expect(fn () => notifications()->preferences()->set($world['editor'], 'sms', false))->toThrow(InvalidNotificationException::class, 'preferences cover')
        ->and(fn () => notifications()->preferences()->set($world['editor'], 'mail', false, 'finance'))->toThrow(InvalidNotificationException::class, 'not registered');
});

it('lets users change only their own preferences, never mandatory ones', function (): void {
    $this->reboot(['gcego-notifications.categories' => ['alerts' => ['mandatory' => ['database']]]]);
    $world = world();
    Livewire\Livewire::component('preferences-page', NotificationPreferences::class);
    $this->actingAs($world['editor']);

    $component = Livewire\Livewire::test(NotificationPreferences::class)
        ->assertSee('In the app')
        ->call('toggle', 'general', 'mail')
        ->call('toggle', 'alerts', 'database')
        ->call('toggle', 'general', 'sms')
        ->call('toggle', 'Bad', 'mail');

    $preferences = notifications()->preferences();

    expect($preferences->allows($world['editor']->fresh(), 'general', 'mail'))->toBeFalse()
        ->and($preferences->allows($world['editor']->fresh(), 'alerts', 'database'))->toBeTrue()
        ->and($preferences->allows($world['reader'], 'general', 'mail'))->toBeTrue()
        ->and(DB::table('gcego_notification_preferences')->count())->toBe(1)
        ->and(squish($component->html()))->toContain('role="switch" aria-checked="false"');
});
