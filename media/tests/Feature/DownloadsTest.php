<?php

declare(strict_types=1);

use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Exceptions\MediaAccessDeniedException;
use Gcego\Media\MediaCollection;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
    $this->subject = $this->world['subjects'][0];
});

it('serves an attached file to users who may view its record, with safe headers', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'Syllabus 2026.pdf'), 'files');
    $response = asMember($this->world['reader'])->get(media()->url($media, download: true));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="Syllabus 2026.pdf"')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('shows viewable types inline, and downloads on the download route', function (): void {
    $png = media()->attach($this->subject, fixtureFile('png', 'chart.png'), 'files');
    $csv = media()->attach($this->subject, fixtureFile('csv', 'scores.csv'), 'files');

    expect(asMember($this->world['admin'])->get(media()->url($png))->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and(asMember($this->world['admin'])->get(media()->url($png, download: true))->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and(asMember($this->world['admin'])->get(media()->url($csv))->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and(asMember($this->world['admin'])->get(media()->url($csv))->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('serves image variants', function (): void {
    $media = media()->attach($this->subject, fixtureFile('png', 'photo.png', 800, 400), 'files')->refresh();
    $response = asMember($this->world['admin'])->get(media()->url($media, 'thumb'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and(asMember($this->world['admin'])->get('/media/'.$media->ulid.'/variants/huge')->getStatusCode())->toBe(404)
        ->and(asMember($this->world['admin'])->get('/media/'.$media->ulid.'/variants/..%2F..%2Fetc')->getStatusCode())->toBe(404);
});

it('refuses users who may not view the record, guests, and unknown ids', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'private.pdf'), 'files');

    expect(asMember($this->world['visitor'])->get(media()->url($media))->getStatusCode())->toBe(403)
        ->and($this->get('/media/'.$media->ulid)->getStatusCode())->toBe(403)
        ->and(asMember($this->world['admin'])->get('/media/01aaaaaaaaaaaaaaaaaaaaaaaa')->getStatusCode())->toBe(404)
        ->and(asMember($this->world['admin'])->get('/media/'.$media->getKey())->getStatusCode())->toBe(404);
});

it('hides other tenants\' media entirely', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'institution-1.pdf'), 'files');

    expect(asMember($this->world['south'], 2)->get(media()->url($media))->getStatusCode())->toBe(404)
        ->and(asMember($this->world['south'], 2)->get(media()->url($media, download: true))->getStatusCode())->toBe(404);
});

it('lets uploaders open their unattached files, and nobody else', function (): void {
    $media = media()->upload(fixtureFile('txt', 'draft.txt'));

    expect(asMember($this->world['admin'])->get(media()->url($media))->getStatusCode())->toBe(200)
        ->and(asMember($this->world['editor'])->get(media()->url($media))->getStatusCode())->toBe(403);
});

it('serves public media through the disk\'s public URL', function (): void {
    media()->collection((new MediaCollection('logos'))->disk('public')->public()->image());
    $media = media()->upload(fixtureFile('png', 'logo.png'), 'logos')->refresh();

    expect(media()->url($media))->toStartWith('http://localhost/storage/gcego-media/')
        ->and($this->get('/media/'.$media->ulid)->getStatusCode())->toBe(200);
});

it('never serves quarantined media', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'flagged.pdf'), 'files');
    $media->forceFill(['status' => MediaStatus::Quarantined])->save();

    expect(asMember($this->world['admin'])->get(media()->url($media))->getStatusCode())->toBe(403);
});

it('answers 404 when the stored file is gone', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'gone.pdf'), 'files');
    app('filesystem')->disk('local')->delete($media->path);

    expect(asMember($this->world['admin'])->get(media()->url($media))->getStatusCode())->toBe(404);
});

it('returns the authorized download from the manager', function (): void {
    $media = media()->attach($this->subject, fixtureFile('pdf', 'direct.pdf'), 'files');

    expect(media()->download(media: $media, user: $this->world['admin'])->headers->get('Content-Disposition'))->toContain('direct.pdf')
        ->and(fn () => media()->download(media: $media, user: $this->world['visitor']))->toThrow(MediaAccessDeniedException::class);
});
