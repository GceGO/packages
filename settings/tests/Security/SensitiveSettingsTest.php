<?php

declare(strict_types=1);

use Gcego\Settings\Events\SecretRotated;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\History\ChangeType;
use Gcego\Settings\History\SettingChange;
use Gcego\Settings\Persistence\SettingRecord;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Transfer\SettingsExporter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

it('encrypts sensitive values at rest with authenticated encryption', function (): void {
    settings()->global()->asSystem()->set('external_api_secret', 'sk_live_123');
    $record = SettingRecord::query()->where('key', 'external_api_secret')->sole();

    expect($record->encrypted)->toBeTrue()
        ->and($record->value)->not->toContain('sk_live_123')
        ->and(decrypt($record->value, false))->toBe('"sk_live_123"')
        ->and(settings()->global()->get('external_api_secret'))->toBe('sk_live_123')
        ->and($record->toArray())->not->toHaveKey('value');
});

it('treats a tampered ciphertext as unreadable instead of returning it', function (): void {
    settings()->global()->asSystem()->set('external_api_secret', 'sk_live_123');
    $record = SettingRecord::query()->where('key', 'external_api_secret')->sole();
    $payload = json_decode(base64_decode($record->value), true);
    $payload['value'] = base64_encode('garbage-bytes-garbage');
    SettingRecord::query()->whereKey($record->id)->update(['value' => base64_encode((string) json_encode($payload))]);
    settings()->flushCache();

    actAs(null);
    expect(settings()->global()->get('external_api_secret'))->toBeNull()
        ->and(settings()->inspect()['problems'])->toContain('1 stored setting values are invalid for their definition: external_api_secret @ global (decrypt).');
});

it('keeps secrets out of history, events and logs', function (): void {
    Event::fake([SecretRotated::class]);
    Log::spy();
    $this->reboot(['gcego-settings.observability.log_channel' => 'stack']);
    Event::fake([SecretRotated::class]);

    settings()->global()->asSystem()->set('external_api_secret', 'first-secret');
    settings()->global()->asSystem()->rotate('external_api_secret', 'second-secret');

    $history = SettingChange::query()->where('key', 'external_api_secret')->orderBy('id')->get();

    expect($history->pluck('change')->all())->toBe([ChangeType::Updated->value, ChangeType::Rotated->value])
        ->and($history->every(static fn (SettingChange $change): bool => $change->redacted && $change->old_value === null && $change->new_value === null))->toBeTrue();
    Event::assertDispatched(SecretRotated::class, 2);
    Event::assertDispatched(SecretRotated::class, static fn (SecretRotated $event): bool => ! str_contains(serialize($event), 'secret-'.'second') && ! str_contains(serialize($event), 'first-secret'));
});

it('refuses to rotate settings that are not sensitive, or to an empty secret', function (): void {
    expect(fn () => settings()->global()->asSystem()->rotate('site_name', 'x'))->toThrow(InvalidSettingException::class, 'is not sensitive')
        ->and(fn () => settings()->global()->asSystem()->rotate('external_api_secret', ''))->toThrow(InvalidSettingException::class, 'Enter a new value for API secret');
});

it('never exports sensitive values, not even encrypted', function (): void {
    $world = world();
    settings()->global()->asSystem()->setMany(['site_name' => 'Exported', 'external_api_secret' => 'never-leaves']);

    actAs($world['admin']);
    $export = app(SettingsExporter::class)->export(app(ScopeResolver::class)->global(), $world['admin']);

    expect($export['settings'])->toBe(['site_name' => ['type' => 'string', 'value' => 'Exported']])
        ->and($export['redacted'])->toBe(['external_api_secret'])
        ->and(json_encode($export))->not->toContain('never-leaves')->not->toContain('eyJ');
});

it('records safe previous and new values of ordinary settings', function (): void {
    settings()->global()->asSystem()->set('site_name', 'One');
    settings()->global()->asSystem()->set('site_name', 'Two');
    $last = SettingChange::query()->where('key', 'site_name')->latest('id')->firstOrFail();

    expect($last->old_value)->toBe('"One"')
        ->and($last->new_value)->toBe('"Two"')
        ->and($last->redacted)->toBeFalse()
        ->and($last->actor_name)->toBe('system')
        ->and(fn () => $last->forceFill(['new_value' => 'forged'])->save())->toThrow(InvalidSettingException::class, 'append-only')
        ->and(fn () => $last->delete())->toThrow(InvalidSettingException::class, 'append-only');
});
