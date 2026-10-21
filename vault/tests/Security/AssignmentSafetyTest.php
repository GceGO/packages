<?php

declare(strict_types=1);

use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\AuditEntry;
use Gcego\Vault\Models\Role;
use Illuminate\Support\Facades\Date;

beforeEach(fn () => synced());

function refusal(Closure $change): string
{
    try {
        $change();
    } catch (VaultAuthorizationException $exception) {
        return $exception->rule();
    }

    return 'allowed';
}

it('lets an administrator give a role they fully hold, and audits it with its provenance', function (): void {
    role('viewer', ['books.viewAny']);
    $admin = administrator(also: ['books.viewAny']);
    $user = person('User');

    vault()->assignments()->assignRole($admin, $user, 'viewer', reason: 'New librarian');

    $entry = AuditEntry::query()->where('event', AuditEvent::RoleAssigned->value)->latest('id')->firstOrFail();
    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue()
        ->and($entry->actor_id)->toBe((string) $admin->id)
        ->and($entry->subject_id)->toBe((string) $user->id)
        ->and($entry->target)->toBe('role:viewer')
        ->and($entry->reason)->toBe('New librarian')
        ->and($entry->payload['source'])->toBe('manual');
});

it('refuses role changes without the authority to make them', function (): void {
    role('viewer', ['books.viewAny']);
    $clerk = person('Clerk');
    $clerk->assignRole(role('clerk', ['books.viewAny', 'vault.users.viewAny']));

    expect(refusal(fn () => vault()->assignments()->assignRole($clerk, person('Target'), 'viewer')))->toBe('missing_authority');
});

it('refuses self-promotion, even to an administrator', function (): void {
    role('viewer', ['books.viewAny']);
    $admin = administrator(also: ['books.viewAny']);

    expect(refusal(fn () => vault()->assignments()->assignRole($admin, $admin, 'viewer')))->toBe('self_assignment')
        ->and(refusal(fn () => vault()->assignments()->grantPermission($admin, $admin, 'reports.export')))->toBe('self_assignment')
        ->and(AuditEntry::query()->where('event', AuditEvent::EscalationBlocked->value)->count())->toBe(2);
});

it('refuses to give access beyond the giver\'s own, including through inheritance', function (): void {
    role('deleter', ['books.viewAny', 'books.view', 'books.delete']);
    role('wrapper', [], inherits: ['deleter']);
    $admin = administrator();

    expect(refusal(fn () => vault()->assignments()->assignRole($admin, person('A'), 'deleter')))->toBe('beyond_authority')
        ->and(refusal(fn () => vault()->assignments()->assignRole($admin, person('B'), 'wrapper')))->toBe('beyond_authority')
        ->and(refusal(fn () => vault()->assignments()->grantPermission($admin, person('C'), 'books.delete')))->toBe('beyond_authority')
        ->and(Assignment::query()->where('role_id', '!=', Role::query()->where('name', 'vault-admin')->value('id'))->count())->toBe(0);
});

it('refuses to strip access above one\'s own, but always lets people give up their own', function (): void {
    role('deleter', ['books.viewAny', 'books.view', 'books.delete']);
    $admin = administrator();
    $senior = person('Senior');
    $senior->assignRole('deleter');
    $admin->assignRole('deleter');
    vault()->assignments()->revokeRole($admin, $admin, 'deleter');

    expect($admin->hasPermissionTo('books.delete'))->toBeFalse()
        ->and(refusal(fn () => vault()->assignments()->revokeRole($admin, $senior, 'deleter')))->toBe('beyond_authority')
        ->and($senior->hasPermissionTo('books.delete'))->toBeTrue();
});

it('confines tenant authority to its tenant and to its members', function (): void {
    role('teacher', ['grades.update']);
    $tenantAdmin = person('Head', [1, 2]);
    $tenantAdmin->assignRole(role('tenant-admin', ['vault.roles.viewAny', 'vault.users.viewAny', 'vault.roles.assign', 'grades.update']), 'institution:1');
    $member = person('Member', [1, 2]);
    $outsider = person('Outsider', [2]);

    vault()->assignments()->assignRole($tenantAdmin, $member, 'teacher', 'institution:1');

    expect($member->hasPermissionTo('grades.update', 'institution:1'))->toBeTrue()
        ->and(refusal(fn () => vault()->assignments()->assignRole($tenantAdmin, $member, 'teacher', 'institution:2')))->toBe('missing_authority')
        ->and(refusal(fn () => vault()->assignments()->assignRole($tenantAdmin, $outsider, 'teacher', 'institution:1')))->toBe('not_member')
        ->and(refusal(fn () => vault()->assignments()->assignRole($tenantAdmin, $member, 'teacher')))->toBe('missing_authority');
});

it('never lets a role defined by one tenant be used, or even found, in another', function (): void {
    role('north-only', ['grades.update'], tenant: 'institution:1');
    $admin = person('Head', [1, 2]);
    $admin->assignRole(role('tenant-admin', ['vault.roles.viewAny', 'vault.users.viewAny', 'vault.roles.assign', 'grades.update']), 'institution:2');

    $northOnly = Role::query()->where('name', 'north-only')->sole();

    expect(fn () => vault()->assignments()->assignRole($admin, person('M', [2]), 'north-only', 'institution:2'))->toThrow(VaultValidationException::class, 'There is no role "north-only" here.')
        ->and(refusal(fn () => vault()->assignments()->assignRole($admin, person('N', [2]), $northOnly, 'institution:2')))->toBe('foreign_tenant')
        ->and(fn () => person('X', [2])->assignRole($northOnly, 'institution:2'))->toThrow(VaultAuthorizationException::class);
});

it('keeps the last holder of a protected role, with the console as the recovery path', function (): void {
    $this->reboot(['gcego-vault.governance.protected_roles' => ['owner']]);
    synced();
    role('owner', ['books.viewAny']);
    $owner = person('Owner');
    $owner->assignRole('owner');

    expect(fn () => $owner->removeRole('owner'))->toThrow(VaultAuthorizationException::class)
        ->and($owner->hasPermissionTo('books.viewAny'))->toBeTrue();

    $second = person('Second');
    $this->artisan('gcego:vault:assign', ['holder' => $second->email, 'role' => 'owner'])->assertSuccessful();

    expect($owner->removeRole('owner'))->toBeTrue();
});

it('only lets super administrators give super-administrator roles, and only globally', function (): void {
    $this->reboot(['gcego-vault.super_admin.enabled' => true]);
    synced();
    $root = role('root');
    $root->forceFill(['is_super' => true])->save();
    app(Invalidation::class)->definitions();
    $admin = administrator();
    $superUser = person('Super');
    $superUser->assignRole('root');

    expect(refusal(fn () => vault()->assignments()->assignRole($admin, person('A'), 'root')))->toBe('super_required')
        ->and(fn () => person('B', [1])->assignRole('root', 'institution:1'))->toThrow(VaultValidationException::class);

    vault()->assignments()->assignRole($superUser, person('C'), 'root');
    expect(Assignment::query()->where('role_id', $root->id)->active()->count())->toBe(2);
});

it('requires an approved request for sensitive access when configured', function (): void {
    $this->reboot(['gcego-vault.governance.sensitive_requires_approval' => true]);
    synced();
    $admin = administrator(also: ['reports.export']);

    expect(refusal(fn () => vault()->assignments()->grantPermission($admin, person('A'), 'reports.export')))->toBe('approval_required');
});

it('gives the same access once, even when assigned twice, and only changes its expiry', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Twice');
    $user->assignRole('viewer');
    $user->assignRole('viewer', until: Date::now()->addDay());

    expect(Assignment::query()->count())->toBe(1)
        ->and(Assignment::query()->sole()->expires_at)->not->toBeNull();
});

it('rejects expiry in the past and beyond the configured maximum', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Time');

    expect(fn () => $user->assignRole('viewer', until: Date::now()->subMinute()))->toThrow(VaultValidationException::class)
        ->and(fn () => $user->assignRole('viewer', until: Date::now()->addDays(400)))->toThrow(VaultValidationException::class);
});

it('closes and audits expired access, and allows giving it again', function (): void {
    role('viewer', ['books.viewAny']);
    $user = person('Expiring');
    $user->assignRole('viewer', until: Date::now()->addHour());
    $this->travel(2)->hours();

    $this->artisan('gcego:vault:expire')->assertSuccessful();

    expect(Assignment::query()->sole()->status())->toBe('expired')
        ->and(AuditEntry::query()->where('event', AuditEvent::AccessExpired->value)->count())->toBe(1);

    $user->assignRole('viewer');
    expect($user->hasPermissionTo('books.viewAny'))->toBeTrue()->and(Assignment::query()->count())->toBe(2);
});
