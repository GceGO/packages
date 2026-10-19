<?php

declare(strict_types=1);

use Gcego\Media\Definitions\Fit;
use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Definitions\ScanStatus;
use Gcego\Media\Definitions\Variant;
use Gcego\Media\Exceptions\MediaProcessingException;
use Gcego\Media\Jobs\ProcessMedia;
use Gcego\Media\MediaCollection;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Tests\Fixtures\Scanning\FakeScanner;
use Gcego\Media\Transformations\MediaProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['admin']);
});

function variantSize(MediaRecord $media, string $variant): array
{
    $size = getimagesizefromstring((string) app('filesystem')->disk($media->disk)->get((string) $media->variant($variant)['path']));

    return [$size[0], $size[1], $size['mime']];
}

it('generates variants: cover crops, contain fits, width scales, formats convert', function (): void {
    media()->collection((new MediaCollection('gallery'))->image()->variants(
        Variant::make('square', 200, 200, Fit::Cover, 'jpg'),
        Variant::make('box', 300, 300, Fit::Contain, 'png'),
        Variant::make('wide', 400),
        Variant::make('tiny', 2000, 2000, Fit::Contain),
    ));
    $media = media()->upload(fixtureFile('png', 'landscape.png', 1200, 600), 'gallery')->refresh();

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and(variantSize($media, 'square'))->toBe([200, 200, 'image/jpeg'])
        ->and(variantSize($media, 'box'))->toBe([300, 150, 'image/png'])
        ->and(variantSize($media, 'wide'))->toBe([400, 200, 'image/webp'])
        ->and(variantSize($media, 'tiny'))->toBe([1200, 600, 'image/webp'])
        ->and($media->variant('square')['signature'])->toMatch('/^[0-9a-f]{12}$/');
});

it('queues processing after the commit, uniquely per media', function (): void {
    Queue::fake();
    $media = media()->upload(fixtureFile('png', 'queued.png'));
    $job = Queue::pushed(ProcessMedia::class)->first();

    expect($media->fresh()?->status)->toBe(MediaStatus::Pending)
        ->and($job->queue)->toBe('media')
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->uniqueId())->toBe('gcego-media-'.$media->getKey())
        ->and($job->tries)->toBe(3);
});

it('is idempotent: unchanged variants are kept, changed definitions regenerate', function (): void {
    $media = media()->upload(fixtureFile('png', 'same.png', 640, 480))->refresh();
    $path = $media->variant('thumb')['path'];
    $modified = app('filesystem')->disk('local')->lastModified($path);
    $this->travel(5)->seconds();
    app(MediaProcessor::class)->process($media);

    expect($media->refresh()->variant('thumb')['path'])->toBe($path)
        ->and($media->status)->toBe(MediaStatus::Ready)
        ->and(app('filesystem')->disk('local')->lastModified($path))->toBe($modified);

    media()->collection((new MediaCollection('default'))->variants(Variant::make('thumb', 100, 100, Fit::Cover)));
    app(MediaProcessor::class)->process($media->refresh());

    expect(variantSize($media->refresh(), 'thumb'))->toBe([100, 100, 'image/webp']);
});

it('skips media that is already being processed', function (): void {
    $media = media()->upload(fixtureFile('png', 'busy.png'))->refresh();
    $media->forceFill(['status' => MediaStatus::Processing, 'variants' => null])->save();

    expect(app(MediaProcessor::class)->process($media)->variants)->toBeNull();
});

it('records processing failures, keeps the original and retries through the queue', function (): void {
    $media = media()->upload(fixtureFile('png', 'fragile.png'))->refresh();
    app('filesystem')->disk('local')->delete($media->path);

    expect(fn () => app(MediaProcessor::class)->process($media))->toThrow(MediaProcessingException::class, 'missing')
        ->and($media->refresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->meta('processing_error'))->toContain('missing')
        ->and((new ProcessMedia((int) $media->getKey()))->backoff)->toBe([10, 60, 300]);
});

it('keeps metadata minimal and safe', function (): void {
    $media = media()->upload(fixtureFile('jpg', 'portrait.jpg', 300, 600))->refresh();

    expect($media->metadata)->toBe(['width' => 300, 'height' => 600, 'aspect_ratio' => 0.5, 'orientation' => 'portrait', 'format' => 'jpeg'])
        ->and(media()->upload(fixtureFile('txt', 'plain.txt'))->refresh()->metadata)->toBeNull();
});

it('reads audio and video duration with ffprobe when available', function (): void {
    $ffmpeg = trim((string) shell_exec('command -v ffmpeg'));

    if ($ffmpeg === '' || trim((string) shell_exec('command -v ffprobe')) === '') {
        $this->markTestSkipped('ffmpeg/ffprobe are not installed.');
    }

    $path = tempnam(sys_get_temp_dir(), 'gcego-video-').'.mp4';
    shell_exec(escapeshellarg($ffmpeg).' -loglevel error -f lavfi -i color=c=blue:s=160x120:d=2 -pix_fmt yuv420p -y '.escapeshellarg($path));
    $media = media()->upload(new UploadedFile($path, 'clip.mp4', null, null, true))->refresh();

    expect($media->mime)->toBe('video/mp4')
        ->and($media->meta('duration'))->toEqualWithDelta(2.0, 0.2)
        ->and($media->meta('width'))->toBe(160)
        ->and($media->meta('video_codec'))->toBeString();
});

it('quarantines infected files and files the scanner could not check, and serves clean ones', function (): void {
    $this->reboot(['gcego-media.scanning.scanner' => FakeScanner::class]);
    $world = world();
    actAs($world['admin']);
    $infected = fixtureFile('txt', 'eicar.txt');
    file_put_contents($infected->getRealPath(), 'X5O EICAR test');
    $broken = fixtureFile('txt', 'down.txt');
    file_put_contents($broken->getRealPath(), 'SCANNER-DOWN');

    $bad = media()->upload($infected)->refresh();
    $unknown = media()->upload($broken)->refresh();
    $clean = media()->upload(fixtureFile('txt', 'fine.txt'))->refresh();

    expect($bad->status)->toBe(MediaStatus::Quarantined)
        ->and($bad->scan_status)->toBe(ScanStatus::Infected)
        ->and($unknown->status)->toBe(MediaStatus::Quarantined)
        ->and($unknown->scan_status)->toBe(ScanStatus::Error)
        ->and($clean->scan_status)->toBe(ScanStatus::Clean)
        ->and(asMember($world['admin'])->get(media()->url($bad))->getStatusCode())->toBe(403)
        ->and(asMember($world['admin'])->get(media()->url($clean))->getStatusCode())->toBe(200);
});

it('never claims a file is clean without a scanner', function (): void {
    expect(media()->upload(fixtureFile('txt', 'unscanned.txt'))->refresh()->scan_status)->toBe(ScanStatus::Unscanned);
});
