<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Settings\Exceptions\SettingNotFoundException;
use Gcego\Settings\Exceptions\SettingScopeException;
use Gcego\Settings\Scopes\SettingScope;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\Tests\Fixtures\Enums\Theme;

it('returns typed defaults when nothing is stored', function (): void {
    expect(settings()->get('site_name'))->toBe('GCEGO')
        ->and(settings()->get('maintenance_mode'))->toBeFalse()
        ->and(settings()->get('max_upload_mb'))->toBe(10)
        ->and(settings()->get('preferred_theme'))->toBe(Theme::System)
        ->and(settings()->get('launch_date'))->toBeInstanceOf(CarbonImmutable::class)
        ->and(settings()->get('launch_date')->format('Y-m-d'))->toBe('2026-09-01')
        ->and(settings()->get('external_api_secret'))->toBeNull()
        ->and(settings()->resolve('site_name')->isDefault())->toBeTrue();
});

it('resolves user, tenant, panel and global values by precedence', function (): void {
    $world = world();
    settings()->global()->asSystem()->setMany(['site_name' => 'Platform', 'preferred_theme' => 'light']);
    settings()->forPanel('desk')->asSystem()->set('site_name', 'Desk');
    settings()->forTenant(1)->asSystem()->setMany(['site_name' => 'North', 'preferred_theme' => 'dark']);
    settings()->forUser($world['teacher'])->asSystem()->set('preferred_theme', Theme::System);

    actAs($world['teacher'], 1);
    $teacher = settings()->resolve('preferred_theme');

    expect(settings()->get('site_name'))->toBe('North')
        ->and($teacher->value)->toBe(Theme::System)
        ->and($teacher->source)->toBe(SettingScope::User)
        ->and(settings()->forTenant(1)->get('preferred_theme'))->toBe(Theme::Dark)
        ->and(settings()->forPanel('desk')->get('site_name'))->toBe('Desk')
        ->and(settings()->global()->get('site_name'))->toBe('Platform');

    actAs($world['south'], 2);
    expect(settings()->get('site_name'))->toBe('Platform')
        ->and(settings()->resolve('preferred_theme')->source)->toBe(SettingScope::Global)
        ->and(settings()->forTenant(2)->get('preferred_theme'))->toBe(Theme::Light);
});

it('refuses to store a setting at a scope it does not allow', function (string $key, string $scope): void {
    $world = world();
    $target = match ($scope) {
        'tenant' => settings()->forTenant(1),
        'user' => settings()->forUser($world['teacher']),
        default => settings()->global(),
    };

    expect(fn () => $target->asSystem()->set($key, 1))->toThrow(SettingScopeException::class, sprintf('cannot be stored at the %s scope', $scope));
})->with([
    'platform security per institution' => ['session_lifetime', 'tenant'],
    'site name per person' => ['site_name', 'user'],
    'institution setting globally' => ['school_name', 'global'],
]);

it('keeps an explicitly stored null apart from a missing value', function (): void {
    settings()->global()->asSystem()->set('webhook_url', 'https://hooks.test/a');
    settings()->forTenant(1)->asSystem()->set('max_upload_mb', 20);
    settings()->global()->asSystem()->set('webhook_url', null);

    $webhook = settings()->global()->resolve('webhook_url');

    expect($webhook->value)->toBeNull()
        ->and($webhook->isOverridden())->toBeTrue()
        ->and($webhook->version)->toBe(2)
        ->and(settings()->global()->resolve('site_description')->isDefault())->toBeTrue();
});

it('resets an override so the inherited value applies again', function (): void {
    settings()->global()->asSystem()->set('site_name', 'Platform');
    settings()->forTenant(1)->asSystem()->set('site_name', 'North');

    $result = settings()->forTenant(1)->asSystem()->reset('site_name');

    expect($result->applied)->toBe(['site_name' => 0])
        ->and(settings()->forTenant(1)->resolve('site_name')->source)->toBe(SettingScope::Global)
        ->and(settings()->forTenant(1)->get('site_name'))->toBe('Platform')
        ->and(settings()->definitions()->has('site_name'))->toBeTrue()
        ->and(settings()->forTenant(1)->asSystem()->reset('site_name')->unchanged)->toBe(['site_name']);
});

it('honours a setting\'s own precedence', function (): void {
    settings()->register(new SettingsGroup('locked', new Setting(name: 'enforced_locale', default: 'en', scopes: ['global', 'tenant', 'user'], precedence: ['tenant', 'user', 'global'])));
    $world = world();
    settings()->forTenant(1)->asSystem()->set('enforced_locale', 'fr');
    settings()->forUser($world['teacher'])->asSystem()->set('enforced_locale', 'de');

    actAs($world['teacher'], 1);
    expect(settings()->get('enforced_locale'))->toBe('fr');

    actAs($world['teacher']);
    expect(settings()->get('enforced_locale'))->toBe('de');
});

it('isolates tenants: one institution never sees another\'s values', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('school_name', 'North College');

    actAs($world['south'], 2);
    expect(settings()->get('school_name'))->toBe('')
        ->and(settings()->forTenant(2)->resolve('school_name')->isDefault())->toBeTrue();
});

it('refuses unknown keys and tenants', function (): void {
    expect(fn () => settings()->get('not_registered'))->toThrow(SettingNotFoundException::class)
        ->and(fn () => settings()->asSystem()->set('app.key', 'x'))->toThrow(SettingNotFoundException::class)
        ->and(fn () => settings()->forTenant(99))->toThrow(SettingScopeException::class, 'does not exist');
});

it('reads several settings and whole groups at once', function (): void {
    settings()->global()->asSystem()->set('page_size', 50);

    expect(settings()->many(['page_size', 'table_density']))->toBe(['page_size' => 50, 'table_density' => 'comfortable'])
        ->and(array_keys(settings()->group('preferences')))->toBe(['preferred_theme', 'table_density', 'page_size']);
});

it('writes unscoped changes to the most specific level each setting allows', function (): void {
    $world = world();
    actAs($world['principal'], 1);

    settings()->set('preferred_theme', 'dark');
    settings()->set('school_name', 'North');

    actAs($world['principal'], 1);
    expect(settings()->resolve('preferred_theme')->source)->toBe(SettingScope::User)
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('North')
        ->and(fn () => settings()->setMany(['preferred_theme' => 'light', 'school_name' => 'Other']))->toThrow(SettingScopeException::class, 'stored at different scopes');
});
