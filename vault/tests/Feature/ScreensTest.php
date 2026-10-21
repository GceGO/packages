<?php

declare(strict_types=1);

use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Livewire\AccessRequestsScreen;
use Gcego\Vault\Livewire\ApprovalQueue;
use Gcego\Vault\Livewire\AuditHistory;
use Gcego\Vault\Livewire\Diagnostics;
use Gcego\Vault\Livewire\PermissionMatrix;
use Gcego\Vault\Livewire\PersonAccess;
use Gcego\Vault\Livewire\RoleDetails;
use Gcego\Vault\Livewire\RoleEditor;
use Gcego\Vault\Livewire\RolesIndex;
use Gcego\Vault\Models\AccessRequest;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Support\HolderReference;
use Gcego\Vault\Tests\Fixtures\Models\User;
use Livewire\Livewire;

beforeEach(fn () => synced());

function staff(string $name = 'Admin', array $also = []): User
{
    return administrator($name, ['panels.desk.access', 'books.viewAny', 'books.view', 'books.update', ...$also]);
}

dataset('screens', [
    'roles' => ['/desk/vault', 'vault.roles.viewAny'],
    'new role' => ['/desk/vault/roles/create', 'vault.roles.create'],
    'permissions' => ['/desk/vault/permissions', 'vault.permissions.viewAny'],
    'matrix' => ['/desk/vault/matrix', 'vault.roles.viewAny'],
    'people' => ['/desk/vault/people', 'vault.users.viewAny'],
    'temporary' => ['/desk/vault/temporary', 'vault.users.viewAny'],
    'approvals' => ['/desk/vault/approvals', 'vault.requests.review'],
    'audit' => ['/desk/vault/audit', 'vault.audit.viewAny'],
    'diagnostics' => ['/desk/vault/diagnostics', 'vault.diagnostics.view'],
    'settings' => ['/desk/vault/settings', 'vault.settings.view'],
]);

it('opens each screen for holders of its permission and refuses everyone else', function (string $url, string $permission): void {
    $this->actingAs(staff())->get($url)->assertOk();

    $other = person('Other');
    $other->assignRole(role('panel-user', ['panels.desk.access', ...array_values(array_diff(array_keys(VaultPermissions::definitions()), [$permission]))]));
    app()->forgetScopedInstances();

    $this->actingAs($other)->get($url)->assertForbidden();
})->with('screens');

it('lets anyone signed in into the panel open their own access requests', function (): void {
    $user = person('Plain');
    $user->assignRole(role('panel-user', ['panels.desk.access']));

    $this->actingAs($user)->get('/desk/vault/requests')->assertOk()->assertSee('New request');
});

it('lists only the Vault screens a person may open in the panel navigation', function (): void {
    $user = person('Reviewer');
    $user->assignRole(role('reviewer', ['panels.desk.access', 'vault.users.viewAny', 'vault.requests.review']));

    $html = $this->actingAs($user)->get('/desk/vault/approvals')->assertOk()->getContent();

    expect($html)->toContain('/desk/vault/approvals')->toContain('/desk/vault/people')
        ->not->toContain('/desk/vault/permissions')->not->toContain('/desk/vault/audit');
});

it('creates a role from the editor with its permissions', function (): void {
    $this->actingAs(staff());

    Livewire::test(RoleEditor::class)
        ->set('name', 'cataloguer')->set('label', 'Cataloguer')
        ->set('permissions', ['books.viewAny', 'books.view'])
        ->call('save')
        ->assertRedirect();

    expect(vault()->roles()->require('cataloguer')->permissions()->pluck('name')->sort()->values()->all())->toBe(['books.view', 'books.viewAny']);
});

it('shows the editor\'s refusal and changes nothing when the role would exceed the editor\'s permissions', function (): void {
    $this->actingAs(staff());

    Livewire::test(RoleEditor::class)
        ->set('name', 'deleter')->set('label', 'Deleter')
        ->set('permissions', ['books.viewAny', 'books.view', 'books.delete'])
        ->call('save')
        ->assertNoRedirect()
        ->assertSet('feedback.tone', 'error');

    expect(Role::query()->where('name', 'deleter')->exists())->toBeFalse();
});

it('saves matrix changes as one governed change, asking first for sensitive permissions', function (): void {
    $role = role('reader', ['books.viewAny']);
    $this->actingAs(staff(also: ['reports.export']));
    $view = vault()->permissions()->require('books.view');
    $export = vault()->permissions()->require('reports.export');

    $matrix = Livewire::test(PermissionMatrix::class, ['roleId' => $role->id])
        ->set('draft.'.$view->id, true)
        ->set('draft.'.$export->id, true)
        ->call('save')
        ->assertSet('confirmingSensitive', true);

    expect($role->permissions()->count())->toBe(1);

    $matrix->call('save')->assertSet('feedback.tone', 'success');
    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['books.view', 'books.viewAny', 'reports.export']);
});

it('refuses a matrix draft holding a permission the editor lacks, whatever the browser sends', function (): void {
    $role = role('reader', ['books.viewAny']);
    $this->actingAs(staff());
    $delete = vault()->permissions()->require('books.delete');

    Livewire::test(PermissionMatrix::class, ['roleId' => $role->id])
        ->set('draft.'.$delete->id, true)
        ->set('draft.'.vault()->permissions()->require('books.view')->id, true)
        ->call('save')
        ->assertSet('feedback.tone', 'error');

    expect($role->permissions()->pluck('name')->all())->toBe(['books.viewAny']);
});

it('assigns and revokes roles from a person\'s access screen', function (): void {
    $role = role('reader', ['books.viewAny']);
    $admin = staff();
    $person = person('Pat');
    $this->actingAs($admin);
    $reference = app(HolderReference::class)->encode($person);

    Livewire::test(PersonAccess::class, ['holder' => $reference])->set('role', (string) $role->id)->call('assignRole')->assertSet('feedback.tone', 'success');
    expect($person->hasPermissionTo('books.viewAny'))->toBeTrue();

    $assignment = $person->vaultAssignments()->active()->sole();
    Livewire::test(PersonAccess::class, ['holder' => $reference])->call('confirmRevoke', 'role', $assignment->id)->call('revoke')->assertSet('feedback.tone', 'success');

    app()->forgetScopedInstances();
    expect($person->hasPermissionTo('books.viewAny'))->toBeFalse();
});

it('refuses self-promotion through the person screen', function (): void {
    $role = role('reader', ['books.viewAny']);
    $admin = staff();
    $this->actingAs($admin);

    Livewire::test(PersonAccess::class, ['holder' => app(HolderReference::class)->encode($admin)])
        ->set('role', (string) $role->id)->call('assignRole')
        ->assertSet('feedback.tone', 'error');

    expect($admin->vaultAssignments()->where('role_id', $role->id)->exists())->toBeFalse();
});

it('answers 404 for a role of another tenant named by id', function (): void {
    $foreign = role('south-only', [], tenant: 'institution:2');
    $this->actingAs(staff());

    $this->get('/desk/vault/roles/'.$foreign->id)->assertNotFound();
    Livewire::test(RolesIndex::class)->call('restore', $foreign->id)->assertNotFound();
});

it('stops a Livewire screen on the next update once the viewer\'s access is revoked', function (): void {
    role('reader', ['books.viewAny']);
    $admin = staff();
    $this->actingAs($admin);
    $screen = Livewire::test(RolesIndex::class)->assertOk();

    $admin->removeRole('vault-admin');
    app()->forgetScopedInstances();

    $screen->set('search', 'x')->assertForbidden();
});

it('takes a request from the requester to approval in the screens', function (): void {
    role('reader', ['books.viewAny']);
    $asker = person('Asker');
    $asker->assignRole(role('panel-user', ['panels.desk.access']));
    $this->actingAs($asker);

    Livewire::test(AccessRequestsScreen::class)
        ->set('role', (string) Role::query()->where('name', 'reader')->value('id'))
        ->set('reason', 'I catalogue new arrivals every week')
        ->call('submit')
        ->assertSet('feedback.tone', 'success');

    $request = AccessRequest::query()->sole();
    $this->actingAs(staff('Reviewer'));
    app()->forgetScopedInstances();

    Livewire::test(ApprovalQueue::class)->assertSee('I catalogue new arrivals every week')->call('approve', $request->id)->assertSet('feedback.tone', 'success');

    app()->forgetScopedInstances();
    expect($asker->hasPermissionTo('books.viewAny'))->toBeTrue();
});

it('never shows reviewers their own requests', function (): void {
    role('reader', ['books.viewAny']);
    $reviewer = staff('Reviewer');
    vault()->requests()->submit($reviewer, ['permission' => 'books.delete', 'reason' => 'Removing duplicate records']);
    $this->actingAs($reviewer);

    Livewire::test(ApprovalQueue::class)->assertSee('Nothing is waiting for you.');
});

it('explains decisions on the diagnostics screen', function (): void {
    $this->actingAs(staff());
    $person = person('Diag');

    Livewire::test(Diagnostics::class)
        ->set('personRef', app(HolderReference::class)->encode($person))
        ->set('check', 'books.delete')
        ->assertSee('Decision: DENIED')
        ->assertSee('no role or direct grant gives books.delete');
});

it('escapes names everywhere they are shown', function (): void {
    $role = role('xss');
    $role->forceFill(['label' => '<script>alert(1)</script>'])->save();
    app(Invalidation::class)->definitions();

    $this->actingAs(staff())->get('/desk/vault/roles/'.$role->id)->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('exports role definitions and the audit log for their holders only', function (): void {
    role('reader', ['books.viewAny']);
    $this->actingAs(staff());

    Livewire::test(RolesIndex::class)->call('export')->assertFileDownloaded();
    Livewire::test(AuditHistory::class)->call('export')->assertFileDownloaded();
});

it('shows role members and lets authorized staff assign from the role screen', function (): void {
    $role = role('reader', ['books.viewAny']);
    $this->actingAs(staff());
    $person = person('Member Person');

    Livewire::test(RoleDetails::class, ['role' => (string) $role->id])
        ->set('personSearch', 'Member')
        ->assertSee('Member Person')
        ->call('pick', app(HolderReference::class)->encode($person))
        ->call('assign')
        ->assertSet('feedback.tone', 'success')
        ->assertSee('Member Person');
});
