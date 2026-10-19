<?php

declare(strict_types=1);

use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Operations\SecuredOperation;
use Gcego\Security\Tests\Fixtures\ExportSubject;
use Gcego\Security\Tests\Fixtures\Models\Membership;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->world = world();
    Storage::fake('local');
    Gate::define('subjects.export', fn (User $user): bool => $user->name !== 'Revoked');
    $this->actingAs($this->world['teacher']);
    security()->enterTenant((string) $this->world['north']->id);
});

it('authorizes when the operation is requested', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);

    expect($operation->userKey)->toBe($this->world['teacher']->id)
        ->and($operation->tenant)->toBe('institution:'.$this->world['north']->id)
        ->and($operation->resourceKey)->toBe($this->world['subjects']['algebra']->id)
        ->and(SecuredOperation::fromArray($operation->toArray()))->toEqual($operation)
        ->and(fn () => security()->operation('export', $this->world['subjects']['south']))->not->toThrow(AccessDeniedException::class);

    $this->world['teacher']->update(['name' => 'Revoked']);
    newRequest();

    expect(fn () => security()->operation('export', $this->world['subjects']['algebra']))->toThrow(AccessDeniedException::class);
});

it('re-authorizes when the job runs and writes tenant-separated output', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);
    newRequest();
    auth()->forgetGuards();

    ExportSubject::dispatchSync($operation->toArray());

    Storage::disk('local')->assertExists($operation->storagePath('subject.txt'));
    expect($operation->storagePath('subject.txt'))->toStartWith('gcego/tenants/institution/'.$this->world['north']->id.'/operations/');
});

it('stops the job when the permission is revoked before execution', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);
    $this->world['teacher']->update(['name' => 'Revoked']);
    newRequest();

    expect(fn () => ExportSubject::dispatchSync($operation->toArray()))->toThrow(AccessDeniedException::class);
    Storage::disk('local')->assertMissing($operation->storagePath('subject.txt'));
});

it('stops the job when the membership ends or the user is deleted', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);
    Membership::query()->where('user_id', $this->world['teacher']->id)->update(['status' => 'ended']);
    newRequest();

    expect(fn () => ExportSubject::dispatchSync($operation->toArray()))->toThrow(AccessDeniedException::class);

    Membership::query()->where('user_id', $this->world['teacher']->id)->update(['status' => 'active']);
    $this->world['teacher']->delete();

    expect(fn () => ExportSubject::dispatchSync($operation->toArray()))->toThrow(AccessDeniedException::class);
});

it('does not trust a tampered payload', function (): void {
    $payload = security()->operation('export', $this->world['subjects']['algebra'])->toArray();
    $payload['tenant'] = 'institution:'.$this->world['south']->id;
    $payload['resource_key'] = $this->world['subjects']['south']->id;

    expect(fn () => ExportSubject::dispatchSync($payload))->toThrow(AccessDeniedException::class);
});

it('allows downloads only to the same user in the same tenant', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);
    $path = $operation->storagePath('subject.txt');

    security()->authorizeDownload($operation, $path);

    expect(fn () => security()->authorizeDownload($operation, 'gcego/tenants/institution/'.$this->world['south']->id.'/operations/'.$operation->id.'/subject.txt'))->toThrow(AccessDeniedException::class)
        ->and(fn () => security()->authorizeDownload($operation, $operation->storagePrefix().'../other/subject.txt'))->toThrow(AccessDeniedException::class)
        ->and(fn () => $operation->storagePath('../other/subject.txt'))->toThrow(InvalidPermissionException::class);

    // Another member of the same institution.
    $this->actingAs($this->world['admin']);
    newRequest();
    security()->enterTenant((string) $this->world['north']->id);
    expect(fn () => security()->authorizeDownload($operation, $path))->toThrow(AccessDeniedException::class);

    // The same user from another tenant.
    $this->actingAs($this->world['teacher']);
    newRequest();
    expect(fn () => security()->authorizeDownload($operation, $path))->toThrow(AccessDeniedException::class);
});

it('re-checks the original authorization on download', function (): void {
    $operation = security()->operation('export', $this->world['subjects']['algebra']);
    $this->world['teacher']->update(['name' => 'Revoked']);
    newRequest();
    security()->enterTenant((string) $this->world['north']->id);

    expect(fn () => security()->authorizeDownload($operation, $operation->storagePath('subject.txt')))->toThrow(AccessDeniedException::class);
});
