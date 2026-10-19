<?php

declare(strict_types=1);

use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Transfer\SettingsExporter;
use Gcego\Settings\Transfer\SettingsImporter;

/**
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function document(array $settings): array
{
    return ['format' => 'gcego-settings', 'version' => 1, 'scope' => 'tenant', 'settings' => $settings];
}

it('exports the overrides of one tenant, for its own administrators only', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->setMany(['school_name' => 'North', 'sms_api_key' => 'secret']);
    settings()->forTenant(2)->asSystem()->set('school_name', 'South');

    actAs($world['admin'], 1);
    $export = app(SettingsExporter::class)->export(app(ScopeResolver::class)->forTenant(1), $world['admin'], ['institution']);

    expect($export['settings'])->toBe(['school_name' => ['type' => 'string', 'value' => 'North']])
        ->and($export['redacted'])->toBe(['sms_api_key']);

    actAs($world['principal'], 1);
    expect(fn () => app(SettingsExporter::class)->export(app(ScopeResolver::class)->forTenant(1), $world['principal']))->toThrow(SettingAuthorizationException::class)
        ->and(fn () => app(SettingsExporter::class)->export(app(ScopeResolver::class)->forTenant(2), $world['admin']))->toThrow(SettingAuthorizationException::class);
});

it('previews an import: creations, conflicts and refusals', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('school_name', 'North');
    actAs($world['admin'], 1);

    $preview = app(SettingsImporter::class)->preview(document([
        'school_name' => ['type' => 'string', 'value' => 'Renamed'],
        'max_upload_mb' => ['type' => 'integer', 'value' => 50],
        'sms_api_key' => ['type' => 'string', 'value' => 'smuggled'],
        'session_lifetime' => ['type' => 'integer', 'value' => 1],
        'is_admin' => ['value' => true],
        'site_name' => ['type' => 'string', 'value' => ''],
    ]), app(ScopeResolver::class)->forTenant(1), $world['admin']);

    $statuses = array_column($preview->rows, 'status', 'key');
    ksort($statuses);

    expect($statuses)->toBe(['is_admin' => 'refused', 'max_upload_mb' => 'create', 'school_name' => 'update', 'session_lifetime' => 'refused', 'site_name' => 'refused', 'sms_api_key' => 'refused'])
        ->and(array_column($preview->rows, 'reason', 'key')['sms_api_key'])->toBe('Sensitive settings are never imported; set them by hand.')
        ->and(array_column($preview->rows, 'reason', 'key')['is_admin'])->toBe('This setting does not exist here.')
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('North');
});

it('imports atomically, and only with overwrite when it would replace values', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('school_name', 'North');
    actAs($world['admin'], 1);
    $context = app(ScopeResolver::class)->forTenant(1);
    $document = document(['school_name' => ['value' => 'Renamed'], 'max_upload_mb' => ['value' => 50]]);

    expect(fn () => app(SettingsImporter::class)->import($document, $context, $world['admin']))->toThrow(SettingConflictException::class)
        ->and(settings()->forTenant(1)->get('max_upload_mb'))->toBe(10);

    $result = app(SettingsImporter::class)->import($document, $context, $world['admin'], overwrite: true);

    expect(array_keys($result->applied))->toBe(['max_upload_mb', 'school_name'])
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('Renamed');
});

it('refuses invalid documents, unauthorized importers and documents with refused rows', function (): void {
    $world = world();
    $context = static fn () => app(ScopeResolver::class)->forTenant(1);

    actAs($world['admin'], 1);
    expect(fn () => app(SettingsImporter::class)->import(['format' => 'other'], $context(), $world['admin']))->toThrow(InvalidSettingException::class, 'is not a "gcego-settings" document')
        ->and(fn () => app(SettingsImporter::class)->import(document(['session_lifetime' => ['value' => 3]]), $context(), $world['admin']))->toThrow(InvalidSettingException::class, 'cannot be set at this scope');

    actAs($world['principal'], 1);
    expect(fn () => app(SettingsImporter::class)->preview(document([]), $context(), $world['principal']))->toThrow(SettingAuthorizationException::class);
});

it('caps the size of an import', function (): void {
    $this->reboot(['gcego-settings.transfer.max_settings' => 1]);
    $world = world();
    actAs($world['admin'], 1);

    expect(fn () => app(SettingsImporter::class)->preview(document(['a' => [], 'b' => []]), app(ScopeResolver::class)->forTenant(1), $world['admin']))->toThrow(InvalidSettingException::class, 'more than 1 settings');
});
