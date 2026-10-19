<?php

declare(strict_types=1);

use Gcego\Data\Source;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Testing\Tests\Fixtures\Policies\SubjectPolicy;

it('asserts abilities for the acting user', function (): void {
    $world = world();

    GcegoTest::security()
        ->actingAs($world['student'], 1)
        ->assertCan('view', $world['algebra'])
        ->assertCannot('delete', $world['algebra'])
        ->assertCannot('view', $world['draft'])
        ->assertPolicyApplied(Subject::class, SubjectPolicy::class);

    GcegoTest::security()->actingAs($world['admin'], 1)->assertCan('delete', $world['algebra']);
});

it('explains a refusal it did not expect', function (): void {
    $world = world();

    $message = failure(fn () => GcegoTest::security()->actingAs($world['student'], 1)->assertCan('delete', $world['algebra']));

    expect($message)->toContain('GCEGO SECURITY ASSERTION FAILED')->toContain('status = 403')->toContain('reason = policy_denied')->toContain('Tenant: 1');
});

it('asserts requirements, statuses and reasons', function (): void {
    $world = world();
    $security = GcegoTest::security()->actingAs($world['editor'], 1);

    $security->assertAuthorized(new Authenticated, Permission::inTenant('subjects.view'))
        ->assertForbidden(Permission::inTenant('subjects.secrets'), DenialReason::MissingPermission)
        ->assertForbidden(new Ability('view-reports'));

    GcegoTest::security()->actingAs(null)->assertForbidden(new Authenticated, DenialReason::Unauthenticated);
});

it('asserts definition authorization', function (): void {
    $world = world();

    GcegoTest::security()->actingAs($world['student'], 1)
        ->assertDefinitionAllowed('blueprint:subjects')
        ->assertDefinitionDenied('blueprint:unknown', status: 404);

    GcegoTest::security()->actingAs(null)->assertDefinitionDenied('blueprint:subjects');
});

it('distinguishes hidden (404) from forbidden (403) records', function (): void {
    $world = world();
    $source = new Source(Subject::class);

    GcegoTest::security()->actingAs($world['student'], 1)
        ->assertRecordAccessible($source, $world['algebra']->id)
        ->assertRecordForbidden($source, $world['draft']->id)
        ->assertRecordHidden($source, $world['southern']->id)
        ->assertRecordHidden($source, 999);
});

it('asserts field access and rejects unauthorized writes', function (): void {
    $world = world();
    $rules = ['name', new FieldRule('secret_note', read: Permission::inTenant('subjects.secrets'), write: Permission::inTenant('subjects.secrets'), mask: true)];

    GcegoTest::security()->actingAs($world['editor'], 1)
        ->assertFieldAccess($rules, 'name', FieldAccess::Editable)
        ->assertFieldAccess($rules, 'secret_note', FieldAccess::Redacted)
        ->assertWriteRejected($rules, ['secret_note' => 'mine'])
        ->assertWriteRejected($rules, ['undeclared' => 'x']);

    GcegoTest::security()->actingAs($world['admin'], 1)->assertFieldAccess($rules, 'secret_note', FieldAccess::Editable);
});

it('proves tenant isolation for an institution', function (): void {
    $world = world();

    GcegoTest::tenant(1)
        ->actingAs($world['admin'])
        ->assertCurrent()
        ->assertCanAccessTenant()
        ->assertCannotAccessTenant(2)
        ->assertCannotAccessTenant('3')
        ->assertSees(new Source(Subject::class), [$world['algebra'], $world['draft']])
        ->assertCannotSee(new Source(Subject::class), [$world['southern']]);
});

it('fails loudly when a model is not scoped to its tenant', function (): void {
    $world = world();
    $this->reboot(['gcego-security.tenancy.models' => []]);
    $world = world();

    $message = failure(fn () => GcegoTest::tenant(1)->actingAs($world['admin'])->assertCannotSee(new Source(Subject::class), [$world['southern']]));

    expect($message)->toContain('GCEGO TENANT ASSERTION FAILED')->toContain('leaked:')->toContain('tenancy.models');
});

it('refuses a user who is not a member of the tenant', function (): void {
    $world = world();

    expect(fn () => GcegoTest::tenant(2)->actingAs($world['admin']))->toThrow(TenantAccessException::class);
});

it('runs code in a tenant and restores the previous context', function (): void {
    $world = world();
    $tenant = GcegoTest::tenant(1)->actingAs($world['admin']);

    $seen = GcegoTest::tenant(2)->run($world['south'], fn (): ?string => GcegoTest::manager()->environment()->currentTenant());

    expect($seen)->toBe('2');
    $tenant->assertCurrent()->assertRestoresContext(fn () => GcegoTest::tenant(2)->run($world['south'], fn (): bool => true));
});

it('clears the tenant on reset', function (): void {
    $world = world();
    GcegoTest::tenant(1)->actingAs($world['admin'])->assertCurrent();

    GcegoTest::reset();

    GcegoTest::tenant(1)->assertCleared();
    expect(auth()->user())->toBeNull();
});
