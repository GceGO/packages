<?php

declare(strict_types=1);

use Gcego\Vault\Enums\RequestStatus;
use Gcego\Vault\Exceptions\VaultAuthorizationException;
use Gcego\Vault\Exceptions\VaultValidationException;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    synced();
    role('editor', ['books.viewAny', 'books.view', 'books.update']);
});

function reviewer(string $name): Gcego\Vault\Tests\Fixtures\Models\User
{
    return administrator($name, ['books.viewAny', 'books.view', 'books.update', 'reports.export']);
}

it('grants nothing until the request is approved, then grants it until the chosen date', function (): void {
    $user = person('Asker');
    $request = vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Cataloguing the new collection', 'until' => Date::now()->addWeek()]);

    expect($user->hasPermissionTo('books.update'))->toBeFalse()->and($request->status)->toBe(RequestStatus::Pending);

    vault()->requests()->approve(reviewer('Reviewer'), $request, 'Approved for the project');

    expect($request->refresh()->status)->toBe(RequestStatus::Approved)
        ->and($user->hasPermissionTo('books.update'))->toBeTrue()
        ->and($user->vaultAssignments()->sole()->source)->toBe('request')
        ->and($user->vaultAssignments()->sole()->expires_at?->isFuture())->toBeTrue();
});

it('forbids reviewing one\'s own request, as requester or beneficiary', function (): void {
    $admin = reviewer('Admin');
    $request = vault()->requests()->submit($admin, ['permission' => 'books.delete', 'reason' => 'Need to clean up duplicates'], person('Beneficiary'));

    expect(fn () => vault()->requests()->approve($admin, $request))->toThrow(VaultAuthorizationException::class);

    $own = vault()->requests()->submit($asker = person('Self'), ['role' => 'editor', 'reason' => 'I would like to edit']);
    expect(fn () => vault()->requests()->approve($asker, $own))->toThrow(VaultAuthorizationException::class);
});

it('needs the configured number of distinct approvers for sensitive access', function (): void {
    $user = person('Asker');
    $request = vault()->requests()->submit($user, ['permission' => 'reports.export', 'reason' => 'Quarterly board reports']);
    $first = reviewer('First');

    vault()->requests()->approve($first, $request);
    expect($request->refresh()->status)->toBe(RequestStatus::Pending)
        ->and($user->hasPermissionTo('reports.export'))->toBeFalse()
        ->and(fn () => vault()->requests()->approve($first, $request))->toThrow(VaultValidationException::class);

    vault()->requests()->approve(reviewer('Second'), $request);
    expect($request->refresh()->status)->toBe(RequestStatus::Approved)->and($user->hasPermissionTo('reports.export'))->toBeTrue();
});

it('refuses approvals from reviewers who could not give the access themselves', function (): void {
    $request = vault()->requests()->submit(person('Asker'), ['role' => 'editor', 'reason' => 'Cataloguing the new collection']);

    expect(fn () => vault()->requests()->approve(administrator('Weak'), $request))->toThrow(VaultAuthorizationException::class)
        ->and($request->refresh()->status)->toBe(RequestStatus::Pending);
});

it('rejects with a reason, and lets requesters withdraw only their own pending requests', function (): void {
    $user = person('Asker');
    $rejected = vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Cataloguing the new collection']);
    vault()->requests()->reject(reviewer('Reviewer'), $rejected, 'Not this term');

    $open = vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Second attempt for cataloguing']);

    expect($rejected->refresh()->status)->toBe(RequestStatus::Rejected)
        ->and(fn () => vault()->requests()->cancel(person('Stranger'), $open))->toThrow(VaultAuthorizationException::class)
        ->and(vault()->requests()->cancel($user, $open)->status)->toBe(RequestStatus::Cancelled)
        ->and(fn () => vault()->requests()->approve(reviewer('Late'), $open))->toThrow(VaultValidationException::class);
});

it('refuses duplicate pending requests and requests for access already held', function (): void {
    $user = person('Asker');
    vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Cataloguing the new collection']);

    expect(fn () => vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Asking once more please']))->toThrow(VaultValidationException::class);

    $holder = person('Holder');
    $holder->assignRole('editor');
    expect(fn () => vault()->requests()->submit($holder, ['role' => 'editor', 'reason' => 'I want it again please']))->toThrow(VaultValidationException::class);
});

it('only finalizes a request that is still pending in the database', function (): void {
    $user = person('Asker');
    $request = vault()->requests()->submit($user, ['role' => 'editor', 'reason' => 'Cataloguing the new collection']);
    $stale = Gcego\Vault\Models\AccessRequest::query()->findOrFail($request->id);
    vault()->requests()->approve(reviewer('First'), $request);

    // The stale in-memory copy still says "pending"; the database says "approved".
    expect($stale->status)->toBe(RequestStatus::Pending);
    vault()->requests()->approve(reviewer('Second'), $stale);

    expect($user->vaultAssignments()->count())->toBe(1)
        ->and(Gcego\Vault\Models\Approval::query()->count())->toBe(2);
});
