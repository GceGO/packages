<?php

declare(strict_types=1);

use Gcego\Settings\Cache\SettingsCache;
use Gcego\Settings\Persistence\SettingRecord;
use Illuminate\Support\Facades\Cache;

it('serves values from the cache after the first read', function (): void {
    settings()->global()->asSystem()->set('site_name', 'Cached');
    actAs(null);
    expect(queriesDuring(fn () => settings()->get('site_name')))->not->toBe([]);

    actAs(null);
    expect(queriesDuring(fn () => expect(settings()->get('site_name'))->toBe('Cached')))->toBe([])
        ->and(app(SettingsCache::class)->hits)->toBe(1);
});

it('shows a write to every later request at once', function (): void {
    actAs(null);
    expect(settings()->get('site_name'))->toBe('GCEGO');

    settings()->global()->asSystem()->set('site_name', 'Fresh');

    actAs(null);
    expect(settings()->get('site_name'))->toBe('Fresh');
});

it('shows a global change through every inherited tenant and user value', function (): void {
    $world = world();
    actAs($world['teacher'], 1);
    expect(settings()->get('preferred_theme')->value)->toBe('system');

    settings()->global()->asSystem()->set('preferred_theme', 'dark');

    actAs($world['teacher'], 1);
    expect(settings()->get('preferred_theme')->value)->toBe('dark');
});

it('invalidates after a reset', function (): void {
    settings()->global()->asSystem()->set('site_name', 'Override');
    actAs(null);
    settings()->get('site_name');

    settings()->global()->asSystem()->reset('site_name');

    actAs(null);
    expect(settings()->get('site_name'))->toBe('GCEGO');
});

it('keeps tenant values in separate cache entries', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('school_name', 'North');
    settings()->forTenant(2)->asSystem()->set('school_name', 'South');

    actAs($world['principal'], 1);
    expect(settings()->get('school_name'))->toBe('North');

    actAs($world['south'], 2);
    expect(settings()->get('school_name'))->toBe('South');
});

it('never puts sensitive plaintext in the cache', function (): void {
    settings()->global()->asSystem()->set('external_api_secret', 'cache-me-not');
    actAs(null);
    expect(settings()->get('external_api_secret'))->toBe('cache-me-not');

    $store = Cache::store()->getStore();
    $dump = serialize((fn () => $this->storage)->call($store));

    expect($dump)->not->toContain('cache-me-not')->toContain('external_api_secret');
});

it('does not leak context between jobs or requests in a long-running worker', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('site_name', 'North');

    actAs($world['principal'], 1);
    expect(settings()->get('site_name'))->toBe('North');

    // The worker moves on to a job without tenant: scoped services are forgotten.
    app()->forgetScopedInstances();
    app('auth')->forgetGuards();
    expect(settings()->get('site_name'))->toBe('GCEGO');
});

it('flushes every cached value', function (): void {
    settings()->global()->asSystem()->set('site_name', 'Before');
    actAs(null);
    settings()->get('site_name');

    // A change made outside the package (a manual database fix) shows after a flush.
    SettingRecord::query()->where('key', 'site_name')->update(['value' => '"Patched"']);
    settings()->flushCache();

    actAs(null);
    expect(settings()->get('site_name'))->toBe('Patched');
});

it('still works without a cache', function (): void {
    $this->reboot(['gcego-settings.cache.enabled' => false]);
    settings()->global()->asSystem()->set('site_name', 'Uncached');

    actAs(null);
    expect(settings()->get('site_name'))->toBe('Uncached');
});
