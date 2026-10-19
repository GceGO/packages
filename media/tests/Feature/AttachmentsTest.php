<?php

declare(strict_types=1);

use Gcego\Media\Attachments\MediaAttachments;
use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaCollection;
use Gcego\Media\Models\MediaAttachment;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
    $this->subject = $this->world['subjects'][0];
});

it('attaches uploads to a model, in order, and reads them back', function (): void {
    $first = media()->attach(model: $this->subject, file: fixtureFile('pdf', 'syllabus.pdf'), collection: 'lesson-resources');
    $second = media()->attach(model: $this->subject, file: fixtureFile('txt', 'notes.txt'), collection: 'lesson-resources');

    expect(media()->media($this->subject, 'lesson-resources')->pluck('ulid')->all())->toBe([$first->ulid, $second->ulid])
        ->and($this->subject->fresh()?->mediaIn('lesson-resources')->pluck('original_name')->all())->toBe(['syllabus.pdf', 'notes.txt'])
        ->and($this->subject->fresh()?->firstMedia('lesson-resources')?->ulid)->toBe($first->ulid);
});

it('reorders attachments', function (): void {
    $a = media()->attach($this->subject, fixtureFile('txt', 'a.txt'), 'files');
    $b = media()->attach($this->subject, fixtureFile('txt', 'b.txt'), 'files');
    app(MediaAttachments::class)->reorder($this->subject, 'files', [$b->ulid, 'unknown', $a->ulid]);

    expect(media()->media($this->subject, 'files')->pluck('original_name')->all())->toBe(['b.txt', 'a.txt']);
});

it('replaces the file of a single-file collection, keeping the previous media record', function (): void {
    media()->collection((new MediaCollection('cover'))->image()->single());
    $old = media()->attach($this->subject, fixtureFile('png', 'old.png'), 'cover');
    $new = media()->attach($this->subject, fixtureFile('png', 'new.png'), 'cover');

    expect(media()->media($this->subject, 'cover')->pluck('ulid')->all())->toBe([$new->ulid])
        ->and($old->fresh())->not->toBeNull();
});

it('enforces the collection\'s file count', function (): void {
    media()->collection((new MediaCollection('pair'))->maxFiles(2));
    media()->attach($this->subject, fixtureFile('txt', '1.txt'), 'pair');
    media()->attach($this->subject, fixtureFile('txt', '2.txt'), 'pair');

    expect(fn () => media()->attach($this->subject, fixtureFile('txt', '3.txt'), 'pair'))->toThrow(UploadException::class, 'At most 2 files');
});

it('shares media between records and detaching never deletes it', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'shared.pdf'), 'files');
    media()->attach($this->world['subjects'][1], $media, 'files');

    expect(media()->detach($this->subject, $media, 'files'))->toBeTrue()
        ->and($media->fresh())->not->toBeNull()
        ->and(media()->media($this->world['subjects'][1], 'files')->pluck('ulid')->all())->toBe([$media->ulid])
        ->and(app('filesystem')->disk('local')->exists($media->path))->toBeTrue();
});

it('needs the owner record\'s update ability to attach or detach', function (): void {
    $media = media()->attach($this->subject, fixtureFile('txt', 'x.txt'), 'files');
    actAs($this->world['reader']);

    expect(fn () => media()->attach($this->subject, fixtureFile('txt', 'y.txt'), 'files'))->toThrow(UploadException::class, 'You may not upload files here.')
        ->and(fn () => media()->detach($this->subject, $media, 'files'))->toThrow(MediaAccessDeniedException::class)
        ->and(MediaAttachment::query()->count())->toBe(1);
});

it('refuses attaching media whose type the collection does not accept', function (): void {
    media()->collection((new MediaCollection('images'))->image());
    $pdf = media()->upload(fixtureFile('pdf', 'doc.pdf'));

    expect(fn () => media()->attach($this->subject, $pdf, 'images'))->toThrow(UploadException::class, 'not allowed');
});
