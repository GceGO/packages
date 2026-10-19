<?php

declare(strict_types=1);
use Gcego\Settings\Tests\Fixtures\Models\Membership;

it('refuses a form whose tenant changed under it (forged or switched scope)', function (): void {
    $world = world();
    Membership::query()->create(['user_id' => $world['principal']->id, 'institution_id' => 2, 'permissions' => ['settings.manage']]);
    $page = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/school')->assertOk());
    $snapshot = snapshotOf($page, 'gcego-settings-form');

    // The same snapshot, replayed while the session points at institution 2.
    livewireUpdate($snapshot, [['method' => 'save', 'params' => []]], ['data.school_name' => 'Written into the wrong school'], institution: 2)->assertForbidden();

    expect(settings()->forTenant(2)->get('school_name'))->toBe('')
        ->and(settings()->forTenant(1)->get('school_name'))->toBe('');
});

it('refuses edits to locked state such as the scope, versions or loaded values', function (string $property, mixed $value): void {
    $world = world();
    $page = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/school')->assertOk());

    $response = livewireUpdate(snapshotOf($page, 'gcego-settings-form'), [], [$property => $value]);

    expect($response->status())->toBeGreaterThanOrEqual(400);
})->with([
    'scope' => ['scope', 'tenant:institution:2'],
    'page' => ['page', 'security'],
    'versions' => ['versions.school_name', 99],
    'secrets' => ['secrets.sms_api_key', true],
]);

it('saves through a real Livewire request in the verified tenant', function (): void {
    $world = world();
    $page = html($this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/school')->assertOk());

    livewireUpdate(snapshotOf($page, 'gcego-settings-form'), [['method' => 'save', 'params' => []]], ['data.school_name' => 'North College'])->assertOk();

    expect(settings()->forTenant(1)->get('school_name'))->toBe('North College');
});

it('escapes stored values and labels in the page', function (): void {
    $world = world();
    settings()->forTenant(1)->asSystem()->set('school_name', '<script>alert("x")</script>');

    $page = (string) $this->actingAs($world['principal'])->withSession(tenantSession())->get('/desk/settings/school')->assertOk()->getContent();

    expect($page)->not->toContain('<script>alert("x")</script>');
});
