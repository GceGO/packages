<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Luminaries\Icons\Heroicon;
use Gcego\Notifications\Definitions\NotificationAction;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Definitions\NotificationCategory;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Definitions\NotificationPriority;
use Gcego\Notifications\Definitions\NotificationType;
use Gcego\Notifications\Definitions\SafeUrl;
use Gcego\Notifications\Dispatching\DeliveryOptions;
use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationsConfig;

/**
 * @return list<string>
 */
function notificationErrors(Notification $notification): array
{
    return array_map(static fn ($error): string => $error->format(), $notification->validate()->errors());
}

it('accepts the property syntax with predictable defaults', function (): void {
    $notification = new Notification(
        new Property(Notification::Title, 'Operation Completed'),
        new Property(Notification::Body, 'The requested action completed successfully.'),
        new Property(Notification::Type, 'success'),
    );

    expect(notificationErrors($notification))->toBe([])
        ->and($notification->title())->toBe('Operation Completed')
        ->and($notification->notificationType())->toBe(NotificationType::Success)
        ->and($notification->priority())->toBe(NotificationPriority::Normal)
        ->and($notification->category())->toBe('general')
        ->and($notification->icon())->toBe('status.success')
        ->and($notification->channels())->toBeNull()
        ->and((new Notification('Shorthand title', 'and body'))->body())->toBe('and body');
});

it('builds the same definition fluently, without delivering it', function (): void {
    $queries = queriesDuring(static function () use (&$notification): void {
        $notification = Notification::make()
            ->name('reports.ready')
            ->title('Report Ready')
            ->body('Your requested report is available.')
            ->success()
            ->priority('high')
            ->category('system')
            ->icon('clock')
            ->metadata(['report' => 42])
            ->action('View report', '/reports/42', primary: true)
            ->database()->broadcast()->database()
            ->build();
    });

    expect($queries)->toBe([])
        ->and($notification)->toBeInstanceOf(Notification::class)
        ->and($notification->get(Notification::Name))->toBe('reports.ready')
        ->and($notification->priority())->toBe(NotificationPriority::High)
        ->and($notification->channels())->toBe(['database', 'broadcast'])
        ->and($notification->metadata())->toBe(['report' => 42])
        ->and($notification->actions()[0]->toArray())->toBe(['label' => 'View report', 'url' => '/reports/42', 'primary' => true, 'new_tab' => false])
        ->and(notifications()->send(...))->toBeCallable();
});

it('rejects invalid definitions', function (Closure $build, string $message): void {
    expect($build)->toThrow(Exception::class, $message);
})->with([
    'type' => [fn () => Notification::make()->title('x')->type('fatal')->build(), 'fatal'],
    'priority' => [fn () => Notification::make()->title('x')->priority('urgent')->build(), 'urgent'],
    'empty' => [fn () => Notification::make()->build(), 'Title or a Body'],
    'channel' => [fn () => Notification::make()->title('x')->channels(['sms'])->build(), 'toast, database, broadcast and mail'],
    'category id' => [fn () => Notification::make()->title('x')->category('Bad Category')->build(), 'category id'],
    'too many actions' => [fn () => Notification::make()->title('x')->action('a', '/a')->action('b', '/b')->action('c', '/c')->action('d', '/d')->build(), 'at most 3 actions'],
    'metadata objects' => [fn () => Notification::make()->title('x')->metadata(['user' => new stdClass])->build(), 'scalar'],
    'large metadata' => [fn () => Notification::make()->title('x')->metadata(array_fill_keys(array_map(static fn (int $i): string => 'k'.$i, range(1, 20)), str_repeat('x', 400)))->build(), '4 KB'],
    'javascript link' => [fn () => Notification::make()->title('x')->action('Run', 'javascript:alert(1)')->build(), 'root-relative'],
]);

it('normalizes payloads to plain, storable data and back', function (): void {
    $payload = NotificationPayload::of(Notification::make()->title('Report')->body('Ready')->error()->critical()->action('Open', 'https://reports.example.com/1')->metadata(['report' => 1])->build());
    $stored = json_decode((string) json_encode($payload->toArray()), true);
    $restored = NotificationPayload::fromArray($stored);

    expect($stored['gcego'])->toBe(1)
        ->and($restored)->toEqual($payload)
        ->and($payload->isAssertive())->toBeTrue()
        ->and(NotificationPayload::fromArray(['title' => 'Not ours']))->toBeNull()
        ->and(NotificationPayload::fromArray([...$stored, 'actions' => [['label' => 'Bad', 'url' => 'javascript:x']]])?->actions)->toBe([]);
});

it('keeps semantic and canonical icon identifiers when restoring stored payloads', function (string $icon, string $restored): void {
    $stored = NotificationPayload::of(Notification::make()->title('Saved')->build())->toArray();

    expect(NotificationPayload::fromArray([...$stored, 'icon' => $icon])?->icon)->toBe($restored);
})->with([
    'semantic alias' => ['status.success', 'status.success'],
    'canonical name' => ['lucide:circle-check', 'lucide:circle-check'],
    'markup falls back to the info icon' => ['"><script>', 'status.info'],
]);

it('translates titles and bodies in the reader\'s locale when asked to', function (): void {
    $payload = NotificationPayload::of(Notification::make()->title('gcego-notifications::messages.done')->translate()->build());

    app()->setLocale('fr');

    expect($payload->displayTitle())->toBe('Terminé.');
});

it('accepts only safe link URLs', function (mixed $url, bool $safe): void {
    config()->set('app.url', 'http://localhost');

    expect(SafeUrl::check($url))->toBe($safe);
})->with([
    ['/admin/reports/42', true],
    ['https://example.com/report', true],
    ['http://localhost/admin', true],
    ['http://evil.test/admin', false],
    ['//evil.test', false],
    ['javascript:alert(1)', false],
    ['data:text/html,x', false],
    ['/x" onmouseover="alert(1)', false],
    [null, false],
]);

it('knows the generic categories and refuses unknown ones', function (): void {
    $categories = app(NotificationCategories::class);
    $categories->register(new NotificationCategory('academic', 'Academic', mandatory: ['database']));

    expect(array_keys($categories->all()))->toBe(['general', 'system', 'account', 'security', 'academic'])
        ->and($categories->get('academic')->isMandatory('database'))->toBeTrue()
        ->and(fn () => $categories->get('finance'))->toThrow(InvalidNotificationException::class, 'not registered')
        ->and(fn () => Notification::make()->title('x')->category('finance')->database()->sendTo(world()['admin']))->toThrow(InvalidNotificationException::class, 'not registered');
});

it('validates delivery options', function (Closure $options, string $message): void {
    expect($options)->toThrow(InvalidNotificationException::class, $message);
})->with([
    [fn () => new DeliveryOptions(idempotencyKey: 'has spaces'), 'idempotency key'],
    [fn () => new DeliveryOptions(tenant: '1', global: true), 'no tenant'],
    [fn () => new DeliveryOptions(panel: 'Admin'), 'panel name'],
    [fn () => new DeliveryOptions(delay: new DateTimeImmutable('+2 hours'), expiresAt: new DateTimeImmutable('+1 hour')), 'after the scheduled delivery'],
]);

it('validates its configuration', function (array $config, string $message): void {
    expect(fn () => NotificationsConfig::fromArray($config))->toThrow(InvalidNotificationException::class, $message);
})->with([
    [['default_channels' => ['sms']], 'not a channel'],
    [['toasts' => ['position' => 'middle']], 'position'],
    [['toasts' => ['max_visible' => 0]], 'between 1 and 10'],
    [['broadcasting' => ['poll_seconds' => 2]], 'every 10 seconds'],
    [['broadcasting' => ['enabled' => 'yes']], 'true or false'],
    [['database' => ['retention_days' => 0]], 'at least 1'],
    [['categories' => ['Bad Id' => []]], 'category id'],
    [['categories' => ['alerts' => ['mandatory' => ['sms']]]], 'not a database, broadcast or mail channel'],
    [['security' => ['delivery_policy' => stdClass::class]], 'does not implement'],
    [['preferences' => ['defaults' => ['sms' => true]]], 'database, broadcast and mail'],
]);

it('maps types to icons and gives critical toasts no timeout', function (): void {
    $config = app(NotificationsConfig::class);

    expect(array_map(static fn (NotificationType $type): string => $type->icon(), NotificationType::cases()))->toBe(['status.success', 'status.error', 'status.warning', 'status.info', 'status.info'])
        ->and($config->durationFor(NotificationType::Success, NotificationPriority::Normal))->toBe(5000)
        ->and($config->durationFor(NotificationType::Error, NotificationPriority::Normal))->toBe(8000)
        ->and($config->durationFor(NotificationType::Info, NotificationPriority::Critical))->toBe(0)
        ->and(NotificationPriority::Critical->rank())->toBeGreaterThan(NotificationPriority::High->rank());
});

it('never queries while notifications are defined', function (): void {
    expect(queriesDuring(static fn (): Notification => new Notification('Title', new NotificationAction('Open', '/x'))))->toBe([]);
});

it('takes a typed Luminaries icon from the builder', function (): void {
    expect(Notification::make()->name('reports.ready')->title('Report Ready')->icon(Heroicon::DocumentChartBar)->build()->icon())->toBe('heroicon:document-chart-bar');
});
