<?php

declare(strict_types=1);

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

it('reports a working setup with its warnings', function (): void {
    $report = notifications()->inspect();

    expect($report->healthy())->toBeTrue()
        ->and($report->codes())->toBe(['queue.sync', 'mail.driver'])
        ->and($report->channels)->toBe(['toast' => true, 'database' => true, 'broadcast' => false, 'mail' => true])
        ->and($report->infrastructure['channel'])->toBe('gcego.notifications.{hash}')
        ->and($report->categories)->toBe(['general', 'system', 'account', 'security']);
});

it('reports broadcasting without a broadcaster', function (): void {
    $this->reboot(['gcego-notifications.broadcasting.enabled' => true, 'broadcasting.default' => 'null']);

    expect(notifications()->inspect()->codes())->toContain('broadcasting.driver')
        ->and(notifications()->inspect()->codes())->not->toContain('broadcasting.channel');
});

it('reports missing tables', function (): void {
    Schema::drop('gcego_notification_preferences');
    Schema::table('notifications', static fn ($table) => $table->dropColumn('gcego_panel'));

    expect(notifications()->inspect()->codes())->toContain('database.columns', 'preferences.table')
        ->and(notifications()->inspect()->healthy())->toBeFalse();
});

it('contains no notification content or credentials', function (): void {
    config()->set('mail.mailers.smtp.password', 'super-secret');
    $world = world();
    actAs($world['admin']);
    stored($world['editor'], 'Confidential title');

    expect(json_encode(notifications()->inspect()->toArray()))->not->toContain('Confidential')->not->toContain('super-secret');
});

it('prunes expired and, with a retention period, old read notifications', function (): void {
    $world = world();
    actAs($world['admin']);
    $this->travelTo(now()->subDays(40));
    $oldRead = stored($world['editor'], 'Old read');
    stored($world['editor'], 'Old unread');
    repository()->markRead(viewer($world['editor']), [$oldRead]);
    $this->travelBack();
    stored($world['editor'], 'Expired', static fn ($builder) => $builder->expiresAt(now()->addMinute()));
    stored($world['editor'], 'Fresh');
    $this->travel(2)->minutes();

    $this->artisan('gcego:notifications:prune', ['--dry-run' => true])->expectsOutputToContain('1 notifications would be deleted')->assertSuccessful();
    $this->artisan('gcego:notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::query()->count())->toBe(3);

    $this->artisan('gcego:notifications:prune', ['--days' => 30])->assertSuccessful();

    expect(array_map(static fn ($row): string => $row['title'], DatabaseNotification::query()->pluck('data')->all()))->toEqualCanonicalizing(['Old unread', 'Fresh']);
});
