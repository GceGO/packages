<?php

declare(strict_types=1);

use Gcego\Settings\Events\SettingsUpdated;
use Gcego\Settings\Events\SettingUpdated;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\History\SettingChange;
use Gcego\Settings\Persistence\SettingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('stores JSON, versions every write and skips writes of the same value', function (): void {
    $first = settings()->global()->asSystem()->set('max_upload_mb', '25');
    $same = settings()->global()->asSystem()->set('max_upload_mb', 25);
    $second = settings()->global()->asSystem()->set('max_upload_mb', 30);
    $record = SettingRecord::query()->where('key', 'max_upload_mb')->sole();

    expect($first->applied)->toBe(['max_upload_mb' => 1])
        ->and($same->unchanged)->toBe(['max_upload_mb'])
        ->and($second->version('max_upload_mb'))->toBe(2)
        ->and($record->value)->toBe('30')
        ->and($record->type)->toBe('integer')
        ->and($record->scope_key)->toBe('')
        ->and($record->updated_by)->toBe('system:system')
        ->and(SettingChange::query()->where('key', 'max_upload_mb')->count())->toBe(2);
});

it('writes a bulk update atomically: one invalid value changes nothing', function (): void {
    expect(fn () => settings()->global()->asSystem()->setMany(['site_name' => 'Renamed', 'max_upload_mb' => 'lots']))->toThrow(InvalidSettingException::class, 'The setting "max_upload_mb" is invalid');

    expect(SettingRecord::query()->count())->toBe(0)
        ->and(settings()->global()->get('site_name'))->toBe('GCEGO');
});

it('writes only the valid values when a partial update is asked for', function (): void {
    $result = settings()->global()->asSystem()->setMany(['site_name' => 'Renamed', 'max_upload_mb' => 'lots'], partial: true);

    expect($result->applied)->toBe(['site_name' => 1])
        ->and($result->errors)->toHaveKey('max_upload_mb')
        ->and(settings()->global()->get('site_name'))->toBe('Renamed');
});

it('rolls back every value and the history when a later write fails', function (): void {
    settings()->global()->asSystem()->set('site_name', 'Before');

    expect(fn () => settings()->global()->asSystem()->setMany(['site_description' => 'New', 'site_name' => 'After'], ['site_name' => 9]))->toThrow(SettingConflictException::class);

    expect(settings()->global()->get('site_description'))->toBe('')
        ->and(settings()->global()->get('site_name'))->toBe('Before')
        ->and(SettingChange::query()->count())->toBe(1);
});

it('refuses a write carrying an outdated version instead of overwriting', function (): void {
    $loaded = settings()->global()->resolve('site_name')->version;
    settings()->global()->asSystem()->set('site_name', 'First writer', $loaded);

    expect(fn () => settings()->global()->asSystem()->set('site_name', 'Second writer', $loaded))->toThrow(SettingConflictException::class, 'changed after you loaded it')
        ->and(settings()->global()->get('site_name'))->toBe('First writer');

    settings()->global()->asSystem()->set('site_name', 'Retried', settings()->global()->resolve('site_name')->version);
    expect(settings()->global()->get('site_name'))->toBe('Retried');
});

it('refuses a reset carrying an outdated version', function (): void {
    settings()->global()->asSystem()->set('site_name', 'One');
    settings()->global()->asSystem()->set('site_name', 'Two');

    expect(fn () => settings()->global()->asSystem()->reset('site_name', ['site_name' => 1]))->toThrow(SettingConflictException::class)
        ->and(settings()->global()->get('site_name'))->toBe('Two');
});

it('dispatches value-free events only after the transaction commits', function (): void {
    Event::fake([SettingUpdated::class, SettingsUpdated::class]);

    DB::transaction(function (): void {
        settings()->global()->asSystem()->setMany(['site_name' => 'Inside', 'external_api_secret' => 'top-secret']);
        Event::assertNotDispatched(SettingUpdated::class);
    });

    Event::assertDispatched(SettingUpdated::class, 2);
    Event::assertDispatched(SettingsUpdated::class, static fn (SettingsUpdated $event): bool => $event->keys === ['external_api_secret', 'site_name'] && $event->sensitive && $event->scope === 'global');
    Event::assertDispatched(SettingUpdated::class, static fn (SettingUpdated $event): bool => ! str_contains(serialize($event), 'top-secret'));
});

it('dispatches nothing when the surrounding transaction rolls back', function (): void {
    Event::fake([SettingUpdated::class]);

    try {
        DB::transaction(function (): void {
            settings()->global()->asSystem()->set('site_name', 'Doomed');

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(SettingUpdated::class);
    expect(settings()->global()->get('site_name'))->toBe('GCEGO');
});

it('validates dependent settings against the values after the change', function (): void {
    expect(fn () => settings()->global()->asSystem()->set('mail_enabled', true))->toThrow(InvalidSettingException::class, 'Sender address is required while Send mail is set this way')
        ->and(fn () => settings()->global()->asSystem()->set('newsletter_enabled', true))->toThrow(InvalidSettingException::class, 'Newsletter needs Send mail to be turned on first');

    settings()->global()->asSystem()->setMany(['mail_enabled' => true, 'mail_from_address' => 'office@gcego.test', 'newsletter_enabled' => true]);

    expect(fn () => settings()->global()->asSystem()->set('mail_enabled', false))->toThrow(InvalidSettingException::class, 'Newsletter needs Send mail')
        ->and(settings()->global()->get('newsletter_enabled'))->toBeTrue();
});

it('applies declared, field and custom rules', function (string $key, mixed $value, string $message): void {
    expect(fn () => settings()->global()->asSystem()->set($key, $value))->toThrow(InvalidSettingException::class, $message);
})->with([
    'max length' => ['site_name', str_repeat('a', 61), 'must not be greater than 60 characters'],
    'custom rule class' => ['site_name', 'Darn good', 'must be polite'],
    'email field' => ['mail_from_address', 'not-an-email', 'must be a valid email address'],
    'url field' => ['webhook_url', 'javascript:alert(1)', 'must be a valid URL'],
    'numeric bounds' => ['max_upload_mb', 0, 'must be at least 1'],
    'options' => ['table_density', 'cramped', 'The selected Table density is invalid'],
    'required' => ['site_name', '', 'field is required'],
]);
