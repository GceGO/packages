<?php

declare(strict_types=1);

use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaCollection;
use Gcego\Media\Uploads\FileInspector;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
});

function refusal(Closure $upload): ?string
{
    try {
        $upload();
    } catch (UploadException $exception) {
        return $exception->reason;
    }

    return null;
}

it('stores allowed files with their detected type and a generated path', function (string $kind, string $name, string $mime, string $extension): void {
    $media = media()->upload(fixtureFile($kind, $name));

    expect($media->mime)->toBe($mime)
        ->and($media->extension)->toBe($extension)
        ->and($media->original_name)->toBe($name)
        ->and($media->path)->toMatch('#^gcego-media/t[0-9a-f]{24}/default/\d{4}/\d{2}/[0-9a-z]{26}/[0-9a-z]{32}\.'.$extension.'$#')
        ->and($media->path)->not->toContain(pathinfo($name, PATHINFO_FILENAME))
        ->and($media->checksum)->toMatch('/^[0-9a-f]{64}$/');
})->with([
    ['png', 'Diagram.png', 'image/png', 'png'],
    ['jpg', 'photo.JPEG', 'image/jpeg', 'jpg'],
    ['pdf', 'syllabus.pdf', 'application/pdf', 'pdf'],
    ['txt', 'notes.txt', 'text/plain', 'txt'],
]);

it('refuses dangerous and disguised files, whatever their name or claimed type', function (string $kind, string $name, string $reason): void {
    expect(refusal(fn () => media()->upload(fixtureFile($kind, $name))))->toBe($reason);
})->with([
    'php disguised as an image' => ['php', 'avatar.jpg', 'dangerous'],
    'php' => ['php', 'shell.php', 'dangerous'],
    'double extension' => ['png', 'invoice.php.png', 'dangerous'],
    'html page' => ['html', 'page.html', 'dangerous'],
    'html renamed as text' => ['html', 'notes.txt', 'dangerous'],
    'svg with scripts' => ['svg', 'logo.svg', 'svg'],
    'windows executable' => ['exe', 'setup.pdf', 'dangerous'],
    'extension mismatch' => ['png', 'chart.pdf', 'mismatch'],
    'not allowed type' => ['mp3', 'song.mp3', 'type'],
]);

it('enforces size, emptiness, pixel and dimension limits', function (): void {
    media()->collection((new MediaCollection('banners'))->image()->maxSize(1)->dimensions(minWidth: 100));
    $empty = fixtureFile('txt', 'empty.txt');
    file_put_contents($empty->getRealPath(), '');

    expect(refusal(fn () => media()->upload(fixtureFile('png', 'big.png', 600, 600), 'banners')))->toBe('too_large')
        ->and(refusal(fn () => media()->upload(fixtureFile('png', 'small.png', 20, 20), 'banners')))->toBe('dimensions')
        ->and(refusal(fn () => media()->upload($empty)))->toBe('empty');

    $this->reboot(['gcego-media.images.max_pixels' => 10_000]);
    actAs(world()['admin']);

    expect(refusal(fn () => media()->upload(fixtureFile('png', 'wide.png', 200, 100))))->toBe('pixels');
});

it('normalizes original names for display', function (string $claimed, string $expected): void {
    expect(FileInspector::displayName($claimed))->toBe($expected);
})->with([
    ['../../etc/passwd', 'passwd'],
    ['C:\\Users\\ada\\report.pdf', 'report.pdf'],
    ["evil\u{202E}fdp.exe", 'evilfdp.exe'],
    ['"><img src=x>.png', 'img src=x.png'],
    ['   .hidden   ', 'hidden'],
    ['', 'file'],
]);

it('allows SVG only when configured, and never serves it inline', function (): void {
    $this->reboot(['gcego-media.security.allow_svg' => true, 'gcego-media.uploads.allowed_types' => ['image/svg+xml']]);
    $world = world();
    actAs($world['admin']);
    $media = media()->upload(fixtureFile('svg', 'logo.svg'));
    $response = asMember($world['admin'])->get(media()->url($media));

    expect($media->mime)->toBe('image/svg+xml')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('strips EXIF data from images by re-encoding them', function (): void {
    $jpeg = fixtureFile('jpg', 'camera.jpg', 120, 80);
    $with = (string) file_get_contents($jpeg->getRealPath());
    // A minimal APP1 "Exif" segment, right after SOI.
    $exif = "\xFF\xE1".pack('n', 26)."Exif\x00\x00GPS-SECRET-LOCATION";
    file_put_contents($jpeg->getRealPath(), substr($with, 0, 2).$exif.substr($with, 2));

    $media = media()->upload($jpeg);
    $stored = (string) app('filesystem')->disk('local')->get($media->path);

    expect($stored)->not->toContain('GPS-SECRET-LOCATION')
        ->and($media->metadata)->toMatchArray(['width' => 120, 'height' => 80]);
});
