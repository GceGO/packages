<?php

declare(strict_types=1);
use Gcego\Media\Exceptions\MediaAccessDeniedException;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
    $this->media = media()->attach($this->world['subjects'][0], fixtureFile('pdf', 'report.pdf'), 'files');
});

it('issues expiring signed URLs that still authorize the viewer', function (): void {
    $url = media()->signedUrl($this->media, minutes: 10);

    expect($url)->toContain('expires=')->toContain('signature=')
        ->and(asMember($this->world['admin'])->get($url)->getStatusCode())->toBe(200)
        ->and(asMember($this->world['visitor'])->get($url)->getStatusCode())->toBe(403);

    $this->travel(11)->minutes();

    expect(asMember($this->world['admin'])->get($url)->getStatusCode())->toBe(403);
});

it('refuses tampered signatures and parameters', function (): void {
    $url = media()->signedUrl($this->media);

    expect(asMember($this->world['admin'])->get(str_replace('signature=', 'signature=0', $url))->getStatusCode())->toBe(403)
        ->and(asMember($this->world['admin'])->get(preg_replace('/expires=\d+/', 'expires=9999999999', $url))->getStatusCode())->toBe(403);
});

it('binds signed URLs to a user when asked', function (): void {
    $url = media()->signedUrl($this->media, for: $this->world['admin']);

    expect(asMember($this->world['admin'])->get($url)->getStatusCode())->toBe(200)
        ->and(asMember($this->world['editor'])->get($url)->getStatusCode())->toBe(403);
});

it('serves valid signed links without a session only when the application allows it', function (): void {
    $url = media()->signedUrl($this->media);
    app('auth')->guard()->logout();
    app('auth')->forgetGuards();

    expect($this->get($url)->getStatusCode())->toBe(403);

    $this->reboot(['gcego-media.downloads.require_authorization' => false]);
    $world = world();
    actAs($world['admin']);
    $media = media()->attach($world['subjects'][0], fixtureFile('pdf', 'shareable.pdf'), 'files');
    $signed = media()->signedUrl($media);
    app('auth')->guard()->logout();
    app('auth')->forgetGuards();

    expect($this->get($signed)->getStatusCode())->toBe(200)
        ->and($this->get(media()->url($media))->getStatusCode())->toBeIn([403, 404])
        ->and($this->get(str_replace('t=', 't=0', $signed))->getStatusCode())->toBe(403);
});

it('only issues signed URLs to users who may open the file', function (): void {
    actAs($this->world['visitor']);

    expect(fn () => media()->signedUrl($this->media))->toThrow(MediaAccessDeniedException::class);
});
