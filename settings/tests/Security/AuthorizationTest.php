<?php

declare(strict_types=1);

use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Pages\SettingsPageResolver;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Scopes\ScopeResolver;
use Gcego\Settings\Tests\Fixtures\Models\User;

/**
 * @return array{0: User|null, 1: ScopeContext}
 */
function target(?User $user, string $scope, ?User $owner = null): array
{
    actAs($user);
    $scopes = app(ScopeResolver::class);

    return [$user, match ($scope) {
        'global' => $scopes->global(),
        'panel' => $scopes->forPanel('desk'),
        'tenant1' => $scopes->forTenant(1),
        'tenant2' => $scopes->forTenant(2),
        default => $scopes->forUser($owner ?? $user ?? throw new LogicException('no user')),
    }];
}

it('decides who may change which setting where', function (string $who, string $key, string $scope, bool $allowed): void {
    $world = world();
    [$user, $context] = target($world[$who], $scope, $scope === 'other-user' ? $world['teacher'] : null);

    expect(app(SettingsAuthorizer::class)->canUpdate($user, settings()->definition($key), $context))->toBe($allowed);
})->with([
    'platform manager, global' => ['admin', 'site_name', 'global', true],
    'platform manager, panel' => ['admin', 'site_name', 'panel', true],
    'institution admin, global' => ['principal', 'site_name', 'global', false],
    'institution admin, own tenant' => ['principal', 'site_name', 'tenant1', true],
    'institution admin, other tenant' => ['principal', 'site_name', 'tenant2', false],
    'platform manager, tenant without membership' => ['admin', 'school_name', 'tenant2', false],
    'member without permission, own tenant' => ['teacher', 'school_name', 'tenant1', false],
    'other institution admin, tenant 1' => ['south', 'school_name', 'tenant1', false],
    'security officer, security' => ['security', 'session_lifetime', 'global', true],
    'security officer, general' => ['security', 'site_name', 'global', false],
    'platform manager, security (manage ability)' => ['admin', 'session_lifetime', 'global', true],
    'institution admin, security' => ['principal', 'session_lifetime', 'global', false],
    'own preference' => ['teacher', 'preferred_theme', 'user', true],
    'someone else\'s preference' => ['principal', 'preferred_theme', 'other-user', false],
    'scope not allowed' => ['admin', 'session_lifetime', 'tenant1', false],
]);

it('refuses guests and asks trusted code to say asSystem()', function (): void {
    actAs(null);

    expect(fn () => settings()->global()->set('site_name', 'Anonymous'))->toThrow(SettingAuthorizationException::class, 'use asSystem()')
        ->and(settings()->global()->get('site_name'))->toBe('GCEGO');
});

it('refuses unauthorized writes and resets before validating anything', function (): void {
    $world = world();
    settings()->global()->asSystem()->set('site_name', 'Platform');

    actAs($world['principal'], 1);
    expect(fn () => settings()->global()->set('site_name', 'Hijacked'))->toThrow(SettingAuthorizationException::class, 'You may not change the setting "site_name"')
        ->and(fn () => settings()->global()->set('max_upload_mb', 'not even valid'))->toThrow(SettingAuthorizationException::class)
        ->and(fn () => settings()->global()->reset('site_name'))->toThrow(SettingAuthorizationException::class, 'reset')
        ->and(settings()->global()->get('site_name'))->toBe('Platform');
});

it('lets an institution change its own values but never another institution\'s', function (): void {
    $world = world();

    actAs($world['principal'], 1);
    settings()->forTenant(1)->set('school_name', 'North College');

    actAs($world['principal'], 1);
    expect(fn () => settings()->forTenant(2)->set('school_name', 'Taken over'))->toThrow(SettingAuthorizationException::class);

    actAs($world['south'], 2);
    expect(fn () => settings()->forTenant(1)->set('school_name', 'Taken over'))->toThrow(SettingAuthorizationException::class)
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('North College')
        ->and(settings()->forTenant(2)->get('school_name'))->toBe('');
});

it('writes as the person chosen with actingAs, authorized as them', function (): void {
    $world = world();
    actAs(null);

    settings()->forUser($world['teacher'])->actingAs($world['teacher'])->set('preferred_theme', 'dark');

    expect(settings()->forUser($world['teacher'])->get('preferred_theme')->value)->toBe('dark')
        ->and(fn () => settings()->forUser($world['teacher'])->actingAs($world['principal'])->set('preferred_theme', 'light'))->toThrow(SettingAuthorizationException::class);
});

it('opens pages only where the viewer has a verified tenant and passes the page requirements', function (): void {
    $world = world();
    $pages = static fn (User $user): array => array_map(static fn ($screen): string => $screen->page->pageName(), app(SettingsPageResolver::class)->screens($user, null));

    actAs($world['principal'], 1);
    expect($pages($world['principal']))->toBe(['preferences', 'school', 'branding']);

    actAs($world['principal']);
    expect($pages($world['principal']))->toBe(['preferences']);

    actAs($world['teacher'], 1);
    expect($pages($world['teacher']))->toBe(['preferences']);

    actAs($world['admin']);
    expect($pages($world['admin']))->toBe(['general', 'preferences', 'security']);
});
