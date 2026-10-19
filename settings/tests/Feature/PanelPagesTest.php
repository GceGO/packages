<?php

declare(strict_types=1);

it('lists the settings pages a viewer may open, inside the panel', function (): void {
    $world = world();

    $page = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings')->assertOk());

    expect($page)->toContain('aria-label="Settings pages"')
        ->toContain('/desk/settings/school')
        ->toMatch('/href="[^"]*\/desk\/settings\/school"[^>]*>\s*<svg [^>]*data-gcego-icon="heroicon:adjustments-horizontal"/')   // its icon: Alias::SettingsGeneral
        ->toContain('/desk/settings/branding')
        ->not->toContain('/desk/settings/security')
        ->not->toContain('/desk/settings/general')
        ->toContain('My preferences')
        ->toContain('wire:name="gcego-settings-form"');
});

it('shows a page with its scope, the inherited source of each value and masked secrets', function (): void {
    $world = world();
    settings()->global()->asSystem()->set('site_name', 'Platform name');
    settings()->forTenant(1)->asSystem()->setMany(['max_upload_mb' => 40, 'sms_api_key' => 'sms-secret-value']);

    $branding = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/branding')->assertOk());

    expect($branding)->toContain('data-gcego-settings-scope="tenant"')
        ->toContain('This institution · North College')
        ->toContain('Inherited from Platform')
        ->toContain('Set for This institution')
        ->toContain('Use the inherited value for Max upload mb')
        ->not->toContain('session_lifetime')
        ->and(snapshotOf($branding, 'gcego-settings-form'))->toContain('"site_name":"Platform name"');

    $school = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/school')->assertOk());

    expect($school)->toContain('type="password"')
        ->toContain('Secret set (hidden)')
        ->toContain('A value is set. Leave this empty to keep it')
        ->not->toContain('sms-secret-value')
        ->and(snapshotOf($school, 'gcego-settings-form'))->not->toContain('sms-secret-value');
});

it('hides pages the viewer may not open, and tenant pages without a verified tenant', function (): void {
    $world = world();

    // First, before any tenant is in the session.
    $this->actingAs($world['principal'])->get('/desk/settings/school')->assertNotFound();
    $this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/security')->assertNotFound();
    $this->actingAs($world['teacher'])->withSession(tenantSession())->get('/desk/settings/school')->assertNotFound();
    $this->actingAs($world['principal'])->withSession(tenantSession(2))->get('/desk/settings/school')->assertNotFound();
    $this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/Not%20A%20Page')->assertNotFound();
});

it('sends guests to sign in', function (): void {
    $this->get('/desk/settings')->assertRedirect('/login');
});

it('renders setting inputs as intact tags inside the Livewire form', function (): void {
    $world = world();

    // Raw HTML (no whitespace folding): Livewire 4 morph markers inside an opening tag
    // would end it early and print its attributes as text.
    $page = (string) $this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/branding')->assertOk()->getContent();

    expect(preg_match_all('/<(input|select|textarea)\b[^>]*>/s', $page, $tags))->toBeGreaterThan(0)
        ->and(array_filter($tags[0], static fn (string $tag): bool => str_contains($tag, '<!--')))->toBe([])
        ->and($page)->not->toMatch('/>\s*class="block min-h-10/');
});
