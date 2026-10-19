<?php

declare(strict_types=1);

use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\Models\MediaRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
    $this->disk = app('filesystem')->disk('local');
});

it('replaces a file only after the new one is stored, then cleans up the old one', function (): void {
    $media = media()->attach($this->world['subjects'][0], fixtureFile('png', 'v1.png', 400, 300), 'files')->refresh();
    $old = [$media->path, $media->variant('thumb')['path']];
    $replaced = media()->replace($media, fixtureFile('png', 'v2.png', 200, 100))->refresh();

    expect($replaced->ulid)->toBe($media->ulid)
        ->and($replaced->original_name)->toBe('v2.png')
        ->and($replaced->metadata)->toMatchArray(['width' => 200, 'height' => 100])
        ->and($this->disk->exists($replaced->path))->toBeTrue()
        ->and($this->disk->exists($old[0]))->toBeFalse()
        ->and($replaced->variant('thumb')['path'])->toBe($old[1])
        ->and($this->disk->exists($old[1]))->toBeTrue()
        ->and($replaced->processed_at)->not->toBeNull();
});

it('keeps the original when the replacement is refused', function (): void {
    $media = media()->upload(fixtureFile('png', 'keep.png'))->refresh();

    expect(fn () => media()->replace($media, fixtureFile('php', 'evil.png')))->toThrow(UploadException::class)
        ->and($media->refresh()->original_name)->toBe('keep.png')
        ->and($this->disk->exists($media->path))->toBeTrue();
});

it('soft deletes, and permanently deletes with the files', function (): void {
    $soft = media()->upload(fixtureFile('txt', 'soft.txt'));
    $hard = media()->upload(fixtureFile('png', 'hard.png'))->refresh();

    expect(media()->delete($soft))->toBeTrue()
        ->and(MediaRecord::query()->find($soft->getKey()))->toBeNull()
        ->and(MediaRecord::withTrashed()->find($soft->getKey()))->not->toBeNull()
        ->and($this->disk->exists($soft->path))->toBeTrue()
        ->and(media()->delete($hard, permanent: true))->toBeTrue()
        ->and(MediaRecord::withTrashed()->find($hard->getKey()))->toBeNull()
        ->and($this->disk->exists($hard->path))->toBeFalse()
        ->and($this->disk->exists($hard->variant('thumb')['path']))->toBeFalse();
});

it('lets only the uploader (or media managers) replace and delete', function (): void {
    $media = media()->upload(fixtureFile('txt', 'admin.txt'));
    actAs($this->world['editor']);

    expect(fn () => media()->delete($media))->toThrow(MediaAccessDeniedException::class)
        ->and(fn () => media()->replace($media, fixtureFile('txt', 'x.txt')))->toThrow(MediaAccessDeniedException::class);

    Gate::define('gcego-media.manage', static fn ($user): bool => $user->name === 'Editor');

    expect(media()->delete($media))->toBeTrue();
});

it('cleans up orphans and deleted media, but never attached, shared or library media', function (): void {
    $attached = media()->attach($this->world['subjects'][0], fixtureFile('txt', 'attached.txt'), 'files');
    $orphan = media()->attach($this->world['subjects'][0], fixtureFile('txt', 'orphan.txt'), 'files');
    media()->detach($this->world['subjects'][0], $orphan, 'files');
    $library = media()->upload(fixtureFile('txt', 'library.txt'), 'library');
    $deleted = media()->upload(fixtureFile('txt', 'deleted.txt'));
    media()->delete($deleted);
    $this->travel(25)->hours();

    $this->artisan('gcego:media:cleanup', ['--dry-run' => true])->expectsOutputToContain('2 media would be purged')->assertSuccessful();
    $this->artisan('gcego:media:cleanup')->assertSuccessful();

    expect(MediaRecord::withTrashed()->pluck('original_name')->sort()->values()->all())->toBe(['attached.txt', 'library.txt'])
        ->and($this->disk->exists($orphan->path))->toBeFalse()
        ->and($this->disk->exists($attached->path))->toBeTrue();
});

it('reports stored files without records and never deletes them', function (): void {
    $this->disk->put('gcego-media/shared/stray/2026/10/abc/unknown.bin', 'x');

    $this->artisan('gcego:media:cleanup', ['--files' => true, '--dry-run' => true])->expectsOutputToContain('1 files without a media record (not deleted)')->assertSuccessful();

    expect($this->disk->exists('gcego-media/shared/stray/2026/10/abc/unknown.bin'))->toBeTrue();
});

it('cleans up stored files when the database write fails', function (): void {
    MediaRecord::creating(static fn (): bool => throw new RuntimeException('Database unavailable.'));

    expect(fn () => media()->upload(fixtureFile('png', 'lost.png')))->toThrow(UploadException::class, 'could not be stored')
        ->and($this->disk->allFiles('gcego-media'))->toBe([]);
});

it('removes stored files when a surrounding transaction rolls back', function (): void {
    try {
        DB::transaction(function (): void {
            media()->upload(fixtureFile('png', 'rolled-back.png'));

            throw new RuntimeException('The form save failed.');
        });
    } catch (RuntimeException) {
    }

    expect(MediaRecord::query()->count())->toBe(0)
        ->and($this->disk->allFiles('gcego-media'))->toBe([]);
});
