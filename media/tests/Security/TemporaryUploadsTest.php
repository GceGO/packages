<?php

declare(strict_types=1);

use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\Tests\Fixtures\Models\Membership;

it('accepts a temporary upload from the user who uploaded it', function (): void {
    $world = world();
    actAs($world['admin']);
    $file = livewireTemporary('png', 'mine.png');

    expect(media()->attach($world['subjects'][0], $file, 'files')->original_name)->toBe('mine.png');
});

it('refuses another user\'s temporary upload, even with a valid signed reference', function (): void {
    $world = world();
    actAs($world['admin']);
    $file = livewireTemporary('png', 'not-yours.png');
    actAs($world['editor']);

    expect(fn () => media()->attach($world['subjects'][0], $file, 'files'))->toThrow(UploadException::class, 'belongs to someone else');
});

it('refuses temporary uploads that were never finished through Livewire, or expired, or were used', function (): void {
    $world = world();
    actAs($world['admin']);
    $unfinished = livewireTemporary('png', 'direct.png', finish: false);
    $used = livewireTemporary('png', 'once.png');
    $expiring = livewireTemporary('png', 'late.png');

    expect(fn () => media()->upload($unfinished))->toThrow(UploadException::class, 'expired or belongs')
        ->and(media()->upload($used)->original_name)->toBe('once.png')
        ->and(fn () => media()->upload($used))->toThrow(UploadException::class, 'expired or belongs');

    $this->travel(61)->minutes();

    expect(fn () => media()->upload($expiring))->toThrow(UploadException::class, 'expired or belongs');
});

it('refuses temporary uploads in another tenant', function (): void {
    $world = world();
    actAs($world['admin']);
    Membership::query()->create(['user_id' => $world['admin']->id, 'institution_id' => 2]);
    $file = livewireTemporary('png', 'tenant-1.png');
    actAs($world['admin'], 2);

    expect(fn () => media()->upload($file))->toThrow(UploadException::class, 'expired or belongs');
});
