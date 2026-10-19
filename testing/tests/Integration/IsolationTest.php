<?php

declare(strict_types=1);

use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\PanelManager;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Testing\Environment\TestStateResetter;
use Gcego\Testing\GcegoTest;

it('resets user, tenant, panel context, fixtures and custom state', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['admin'], 1);
    app(PanelContextResolver::class)->set(new PanelContext(app(PanelManager::class)->descriptor('admin'), $world['admin'], 'web', null, 'gcego.admin.'));
    $workspace = GcegoTest::fixtures()->workspace();
    $custom = false;
    app(TestStateResetter::class)->using(function () use (&$custom): void {
        $custom = true;
    });

    GcegoTest::reset();

    expect(auth()->user())->toBeNull()
        ->and(GcegoTest::manager()->environment()->currentTenant())->toBeNull()
        ->and(app(PanelContextResolver::class)->current())->toBeNull()
        ->and($workspace->exists())->toBeFalse()
        ->and($custom)->toBeTrue();
});

it('starts every test without a user or tenant', function (): void {
    expect(auth()->user())->toBeNull()->and(GcegoTest::manager()->environment()->currentTenant())->toBeNull();
})->repeat(3);

it('keeps the acting user across a new request but rebuilds per-request services', function (): void {
    $world = world();
    GcegoTest::security()->actingAs($world['admin'], 1);
    $context = app(TenantContext::class);

    GcegoTest::manager()->environment()->newRequest();

    expect(app(TenantContext::class))->not->toBe($context)
        ->and(auth()->id())->toBe($world['admin']->id)
        ->and(GcegoTest::manager()->environment()->currentTenant())->toBeNull();
});

it('prefixes the cache with the parallel token', function (): void {
    expect(config('cache.prefix'))->toBe('gcego_testing_'.(getenv('TEST_TOKEN') ?: '0').'_');
});
