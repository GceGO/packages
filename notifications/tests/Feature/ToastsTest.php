<?php

declare(strict_types=1);

use Gcego\Luminaries\Facades\Luminaries;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Notification;
use Gcego\Notifications\Toasts\ToastQueue;

it('shows toasts on the next page after a redirect', function (): void {
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->followingRedirects()->get('/save')->getContent();
    $toasts = initialToasts($html);

    expect(array_column($toasts, 'title'))->toBe(['Subject created successfully.', 'Report <b>ready</b>'])
        ->and(array_column($toasts, 'type'))->toBe(['success', 'warning'])
        ->and($toasts[1]['actions'][0]['url'])->toBe('/reports/1')
        ->and(initialToasts((string) $this->get('/toasted')->getContent()))->toBe([]);
});

it('sends each type of toast to the current session', function (string $method, string $type, int $duration): void {
    $world = world();
    $this->actingAs($world['admin']);
    $this->get('/toasted');

    $result = Notification::{$method}('Message');

    expect($result->deliveries[0]->channel)->toBe('toast')
        ->and($result->deliveries[0]->status)->toBe(DeliveryStatus::Sent)
        ->and(app(ToastQueue::class)->pull()[0])->toMatchArray(['title' => 'Message', 'type' => $type, 'duration' => $duration]);
})->with([['success', 'success', 5000], ['error', 'error', 8000], ['warning', 'warning', 5000], ['info', 'info', 5000]]);

it('keeps critical toasts until dismissed and announces urgent errors assertively', function (): void {
    $world = world();
    $this->actingAs($world['admin']);
    $this->get('/toasted');
    Notification::make()->title('Database unavailable')->error()->critical()->send();

    expect(app(ToastQueue::class)->pull()[0])->toMatchArray(['duration' => 0, 'assertive' => true]);
});

it('skips toasts without a session, and when disabled', function (): void {
    expect(Notification::success('Nobody sees this')->deliveries[0]->reason)->toBe('no_session');

    $this->reboot(['gcego-notifications.toasts.enabled' => false]);

    expect(Notification::success('Disabled')->deliveries[0]->reason)->toBe('disabled');
});

it('delivers toasts from Livewire actions in the response', function (): void {
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/notifier')->getContent();
    $response = livewireUpdate(snapshotOf($html, 'notifier'), [['method' => 'ping', 'params' => []]])->assertOk();
    $dispatches = $response->json('components.0.effects.dispatches');

    expect($dispatches[0]['name'])->toBe('gcego-toasts')
        ->and(array_column($dispatches[0]['params']['toasts'], 'title'))->toBe(['Subject created successfully.', 'Unable to save the record.']);
});

it('keeps toasts for the next page when a Livewire action redirects', function (): void {
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/notifier')->getContent();
    $response = livewireUpdate(snapshotOf($html, 'notifier'), [['method' => 'pingAndLeave', 'params' => []]])->assertOk();

    expect($response->json('components.0.effects.redirect'))->toBe('/elsewhere')
        ->and($response->json('components.0.effects.dispatches'))->toBeNull()
        ->and(array_column(initialToasts((string) $this->get('/toasted')->getContent()), 'title'))->toBe(['Your report is being prepared.']);
});

it('renders one toast region per page, with live regions and dismiss buttons', function (): void {
    $world = world();
    $html = squish((string) $this->actingAs($world['admin'])->get('/notifier')->getContent());

    expect(substr_count($html, 'x-data="gcegoToasts('))->toBe(1)
        ->and(substr_count($html, '<script data-gcego-toasts>'))->toBe(1)
        ->and($html)->toContain('role="status" aria-live="polite"')
        ->toContain('role="alert" aria-live="assertive"')
        ->toContain('x-text="toast.title"')
        ->toContain('<span class="sr-only">Dismiss notification</span>')
        ->toContain('x-on:mouseenter="pause()"')
        ->toContain('x-on:gcego-action-completed.window="fromAction($event.detail.result)"');
});

it('never renders toast text as HTML', function (): void {
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/hostile')->getContent();

    expect($html)->not->toContain('<img src=x onerror=alert(1)>')
        ->not->toContain('<script>alert(2)')
        ->not->toContain('x-html')
        ->and(initialToasts($html)[0]['title'])->toBe('<img src=x onerror=alert(1)>');
});

it('places toasts where configured and passes the action-result setting to the browser', function (): void {
    $this->reboot(['gcego-notifications.toasts.position' => 'bottom-center', 'gcego-notifications.toasts.action_results' => false]);
    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/toasted')->getContent();

    expect($html)->toContain('fixed inset-x-0 bottom-0 z-50 flex flex-col items-center')
        ->and(toastState($html)['actions'])->toBeFalse();
});

it('shows a toast to the current user only when they are a recipient', function (): void {
    $world = world();
    $this->actingAs($world['admin']);
    $this->get('/toasted');

    $other = Notification::make()->title('For the editor')->toast()->sendTo($world['editor']);
    $self = Notification::make()->title('For me')->toast()->sendTo([$world['editor'], $world['admin']]);

    expect($other->deliveries[0]->reason)->toBe('not_current_user')
        ->and($self->deliveries[0]->status)->toBe(DeliveryStatus::Sent)
        ->and(array_column(app(ToastQueue::class)->pull(), 'title'))->toBe(['For me']);
});

it('renders toast icons on the server through luminaries aliases', function (): void {
    $world = world();

    $html = (string) $this->actingAs($world['admin'])->get('/toasted')->getContent();

    expect($html)->toContain('<template data-gcego-toast-icon="status.success"><svg')
        ->toContain('data-gcego-icon="heroicon:check-circle"')
        ->toContain('<template data-gcego-toast-icon="status.error">');
});

it('follows application overrides of the toast icon aliases', function (): void {
    $world = world();
    Luminaries::alias('status.success', 'lucide:party-popper', override: true);

    $html = (string) $this->actingAs($world['admin'])->get('/toasted')->getContent();

    expect($html)->toContain('data-gcego-icon="lucide:party-popper"')
        ->not->toContain('data-gcego-icon="heroicon:check-circle"');
});
