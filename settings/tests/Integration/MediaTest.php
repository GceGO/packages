<?php

declare(strict_types=1);

use Gcego\Media\Models\MediaRecord;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Tests\Fixtures\Models\User;
use Illuminate\Support\Str;

function mediaFile(?User $uploader, string $tenant, string $visibility = 'private'): MediaRecord
{
    $media = new MediaRecord;
    $media->forceFill([
        'ulid' => strtolower((string) Str::ulid()), 'tenant' => $tenant, 'uploader_type' => $uploader?->getMorphClass(), 'uploader_id' => $uploader?->id,
        'collection' => 'library', 'disk' => 'local', 'path' => 'library/'.Str::random(8).'.png', 'original_name' => 'logo.png',
        'mime' => 'image/png', 'extension' => 'png', 'size' => 10, 'checksum' => str_repeat('a', 64), 'visibility' => $visibility, 'status' => 'ready',
    ])->save();

    return $media;
}

it('stores a media reference the user may use in their tenant', function (): void {
    $world = world();
    $logo = mediaFile($world['principal'], '1');
    actAs($world['principal'], 1);

    settings()->forTenant(1)->set('school_logo', strtoupper($logo->ulid));

    actAs($world['principal'], 1);
    expect(settings()->forTenant(1)->get('school_logo'))->toBe($logo->ulid)
        ->and(settings()->forTenant(1)->media('school_logo')?->is($logo))->toBeTrue();
});

it('refuses media of another tenant or private media of someone else', function (string $owner, string $tenant): void {
    $world = world();
    $file = mediaFile($world[$owner], $tenant);
    actAs($world['principal'], 1);

    expect(fn () => settings()->forTenant(1)->set('school_logo', $file->ulid))->toThrow(InvalidSettingException::class, 'does not reference a file you can use here');
})->with([
    'another tenant' => ['south', '2'],
    'someone else\'s private file' => ['teacher', '1'],
]);
