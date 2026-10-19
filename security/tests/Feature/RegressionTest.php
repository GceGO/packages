<?php

declare(strict_types=1);

use Gcego\Data\DataManager;
use Gcego\Data\Source;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->world = world();

    // A JSON endpoint as an application (or future gcego/tables API) would expose it.
    Route::middleware(['web', 'auth', 'gcego.tenant:required'])->group(function (): void {
        Route::get('/t/{tenant}/subjects', fn (Request $request, DataManager $data) => $data->query(new Source(Subject::class), $request));
        Route::get('/t/{tenant}/subjects/{id}', fn (string $tenant, string $id, SecurityManager $security) => $security->record(new Source(Subject::class), $id));
        Route::patch('/t/{tenant}/subjects/{id}', function (string $tenant, string $id, Request $request, SecurityManager $security) {
            $subject = $security->record(new Source(Subject::class), $id, 'update');
            $fields = $security->fields(['name']);
            $subject->update(app(FieldAuthorizer::class)->writable($request->all(), $fields));

            return $subject;
        });
    });
});

it('lists only the tenant in the URL after verifying membership', function (): void {
    $north = $this->world['north']->id;

    $this->actingAs($this->world['teacher'])->getJson("/t/{$north}/subjects")->assertOk()->assertJsonPath('pagination.total', 2);
    $this->actingAs($this->world['teacher'])->getJson('/t/'.$this->world['south']->id.'/subjects')->assertNotFound();
});

it('blocks insecure direct object references across tenants', function (): void {
    $north = $this->world['north']->id;

    $this->actingAs($this->world['teacher'])->getJson("/t/{$north}/subjects/".$this->world['subjects']['algebra']->id)->assertOk();
    $this->actingAs($this->world['teacher'])->getJson("/t/{$north}/subjects/".$this->world['subjects']['south']->id)->assertNotFound();
    $this->actingAs($this->world['teacher'])->getJson("/t/{$north}/subjects/".$this->world['subjects']['trashed']->id)->assertNotFound();
});

it('rejects privilege escalation and mass assignment through crafted payloads', function (): void {
    $north = $this->world['north']->id;
    $editor = User::query()->create(['name' => 'Editor']);
    Membership::query()->create(['user_id' => $editor->id, 'institution_id' => $north, 'role' => 'teacher']);
    $algebra = $this->world['subjects']['algebra'];

    $this->actingAs($this->world['teacher'])->patchJson("/t/{$north}/subjects/{$algebra->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($editor)->patchJson("/t/{$north}/subjects/{$algebra->id}", ['name' => 'Moved', 'institution_id' => $this->world['south']->id])->assertForbidden();
    $this->actingAs($editor)->patchJson("/t/{$north}/subjects/{$algebra->id}", ['name' => 'Renamed', 'permissions' => ['*']])->assertForbidden();
    $this->actingAs($editor)->patchJson("/t/{$north}/subjects/{$algebra->id}", ['name' => 'Renamed'])->assertOk();

    expect($algebra->refresh()->name)->toBe('Renamed')
        ->and($algebra->institution_id)->toBe($north);
});

it('ignores browser-supplied permissions, roles and tenant ids', function (): void {
    $north = $this->world['north']->id;

    $this->actingAs($this->world['outsider'])
        ->withHeaders(['X-Tenant' => (string) $north, 'X-Permissions' => '*'])
        ->getJson("/t/{$north}/subjects?role=administrator&tenant={$north}")
        ->assertNotFound();
});

it('fails closed when security configuration is missing', function (): void {
    $this->rebuildApplication(['gcego-security.tenancy' => ['enabled' => true, 'resolver' => null]]);
})->throws(MissingAuthorizationContextException::class, 'tenancy.resolver must name');
