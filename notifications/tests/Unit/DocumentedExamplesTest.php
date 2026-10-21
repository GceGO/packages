<?php

declare(strict_types=1);

use Gcego\Notifications\Channels\DatabaseChannel;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Notifications\Toasts\ToastQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

/**
 * Run a README example as written, with $administrator and $recipient defined.
 *
 * @param  array<string, mixed>  $variables
 */
function readmeExample(string $name, array $variables): mixed
{
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = "use Gcego\\Components\\Properties\\Property;\nuse Gcego\\Notifications\\Notification;\n".($match[1] ?? 'throw new RuntimeException("missing example");');

    return (static function () use ($code, $variables): mixed {
        extract($variables);

        return eval($code.';');
    })();
}

it('runs the toast examples', function (): void {
    $world = world();
    $this->actingAs($world['admin'])->get('/toasted');
    readmeExample('toasts', []);

    expect(array_column(app(ToastQueue::class)->pull(), 'type'))->toBe(['success', 'error', 'warning', 'info']);
});

it('runs the delivery examples', function (string $example, array $channels, int $jobs): void {
    Queue::fake();
    $world = world();
    $world['admin']->forceFill(['email' => 'admin@example.com'])->save();
    $world['editor']->forceFill(['email' => 'editor@example.com'])->save();
    actAs($world['admin']);

    readmeExample($example, ['administrator' => $world['admin'], 'recipient' => $world['editor']]);
    $pushed = Queue::pushed(SendQueuedNotifications::class);

    expect($pushed)->toHaveCount($jobs)
        ->and($pushed->map(static fn (SendQueuedNotifications $job): string => $job->channels[0])->unique()->values()->all())->toEqualCanonicalizing($channels);
})->with([
    ['realtime', [DatabaseChannel::class], 1],
    ['report', [DatabaseChannel::class, 'mail'], 4],
]);

it('runs the declarative example', function (): void {
    $world = world();
    actAs($world['admin']);
    $this->reboot(['gcego-notifications.queue.enabled' => false]);
    $world = world();
    actAs($world['admin']);

    readmeExample('declarative', ['recipient' => $world['editor']]);

    expect(DatabaseNotification::query()->value('data')['title'] ?? null)->toBe('Operation Completed');
});

it('documents the DispatchResult API it shows', function (): void {
    expect(method_exists(DispatchResult::class, 'summary') && method_exists(DispatchResult::class, 'failed') && method_exists(DispatchResult::class, 'throw'))->toBeTrue();
});
