<?php

declare(strict_types=1);

use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->world = world();

    Route::middleware(['web', 'auth', 'gcego.tenant:required'])->get('/t/{tenant}/whoami', fn (SecurityManager $security) => [
        'tenant' => $security->context()->tenant?->id(),
        'roles' => $security->context()->membership?->roles,
    ]);
    Route::middleware(['web', 'auth', 'gcego.tenant'])->get('/current', fn (SecurityManager $security) => ['tenant' => $security->context()->tenant?->id()]);
    Route::middleware(['web', 'auth', 'gcego.tenant:required'])->get('/required', fn () => 'ok');
    Route::middleware(['web', 'auth'])->post('/switch/{key}', function (string $key, Request $request, SecurityManager $security) {
        $security->switchTenant($key, $request->session());

        return 'switched';
    });
});

it('enters a tenant the user belongs to', function (): void {
    $this->actingAs($this->world['teacher'])
        ->getJson('/t/'.$this->world['north']->id.'/whoami')
        ->assertOk()
        ->assertExactJson(['tenant' => 'institution:'.$this->world['north']->id, 'roles' => ['teacher']]);
});

it('conceals tenants the user does not belong to, including forged and unknown keys', function (string $key): void {
    $this->actingAs($this->world['teacher'])->getJson('/t/'.$key.'/whoami')->assertNotFound();
})->with([
    'another institution' => fn () => (string) $this->world['south']->id,
    'unknown id' => ['999'],
    'not an id' => ['abc'],
    'injection' => ['1 OR 1=1'],
]);

it('denies suspended tenants and ended memberships', function (): void {
    $this->actingAs($this->world['teacher'])->getJson('/t/'.$this->world['closed']->id.'/whoami')->assertNotFound();
    $this->actingAs($this->world['outsider'])->getJson('/t/'.$this->world['north']->id.'/whoami')->assertNotFound();
});

it('requires a tenant where the route says so', function (): void {
    $this->actingAs($this->world['teacher'])->getJson('/required')->assertForbidden();
    $this->actingAs($this->world['teacher'])->getJson('/current')->assertExactJson(['tenant' => null]);
});

it('switches tenants only to verified memberships and re-verifies the stored choice', function (): void {
    $admin = $this->world['admin'];

    $this->actingAs($admin)->post('/switch/'.$this->world['south']->id)->assertOk();
    $this->actingAs($admin)->getJson('/current')->assertExactJson(['tenant' => 'institution:'.$this->world['south']->id]);

    // Membership ended after switching: the stored choice no longer opens the tenant.
    Membership::query()->where('user_id', $admin->id)->where('institution_id', $this->world['south']->id)->update(['status' => 'ended']);
    $this->actingAs($admin)->getJson('/current')->assertNotFound();

    $this->actingAs($this->world['teacher'])->post('/switch/'.$this->world['south']->id)->assertNotFound();
});

it('never trusts a tenant key from the session for another user', function (): void {
    $this->actingAs($this->world['admin'])->post('/switch/'.$this->world['south']->id)->assertOk();

    // The teacher inherits the session value (shared browser), but has no membership in South.
    $this->actingAs($this->world['teacher'])->getJson('/current')->assertNotFound();
});

it('does not grant a multi-institution user every institution at once', function (): void {
    $this->actingAs($this->world['admin'])->getJson('/t/'.$this->world['north']->id.'/whoami')->assertJsonPath('roles', ['administrator']);
    $this->actingAs($this->world['admin'])->getJson('/t/'.$this->world['south']->id.'/whoami')->assertJsonPath('roles', ['teacher']);
});

it('starts every request without a tenant', function (): void {
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);

    expect(app(TenantContext::class)->has())->toBeTrue();

    newRequest();

    expect(app(TenantContext::class)->has())->toBeFalse()
        ->and(security()->context()->tenant)->toBeNull();
});

it('answers guests at the tenant boundary without revealing tenants', function (): void {
    expect(fn () => app(TenantAuthorizer::class)->verify(null, (string) $this->world['north']->id))->toThrow(TenantAccessException::class);
});
