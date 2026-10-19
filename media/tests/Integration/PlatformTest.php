<?php

declare(strict_types=1);

use Gcego\Core\Packages\PackageManager;
use Gcego\Media\Definitions\MediaStatus;
use Gcego\Media\Jobs\ProcessMedia;
use Gcego\Media\MediaCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('integrates with the GCEGO packages it builds on', function (): void {
    $descriptor = app(PackageManager::class)->descriptor('gcego/media');

    expect($descriptor->requires)->toContain('gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/actions', 'gcego/panels', 'gcego/notifications', 'gcego/security')
        ->and($descriptor->capabilities)->toContain('media.uploads', 'media.downloads')
        ->and(Livewire::exists('gcego-media-library'))->toBeTrue()
        ->and(Livewire::exists('gcego-media-browser'))->toBeTrue()
        ->and(Livewire::exists('gcego-media-uploader'))->toBeTrue()
        ->and(app('router')->getRoutes()->getByName('gcego.media.download')?->gatherMiddleware())->toBe(['web', 'gcego.tenant:optional']);
});

it('keeps its configuration cacheable', function (): void {
    $config = config('gcego-media');

    expect(eval('return '.var_export($config, true).';'))->toBe($config);
});

it('stores on S3-compatible disks through Laravel\'s filesystem', function (): void {
    $this->reboot(['gcego-media.disks' => ['local', 'public', 's3'], 'filesystems.disks.s3' => ['driver' => 's3', 'bucket' => 'test']]);
    Storage::fake('s3');
    $world = world();
    actAs($world['admin']);
    media()->collection((new MediaCollection('cloud'))->disk('s3'));
    $media = media()->upload(fixtureFile('pdf', 'cloud.pdf'), 'cloud');

    Storage::disk('s3')->assertExists($media->path);

    expect($media->disk)->toBe('s3')
        ->and(asMember($world['admin'])->get(media()->url($media, download: true))->getStatusCode())->toBe(200);
});

it('notifies the uploader when processing finishes or fails, when enabled', function (): void {
    $this->reboot(['gcego-media.notifications.processing' => true, 'gcego-notifications.queue.enabled' => false]);
    $world = world();
    actAs($world['admin']);
    $media = media()->upload(fixtureFile('png', 'notify.png'));
    (new ProcessMedia((int) $media->getKey()))->failed(new RuntimeException('Disk full'));
    $titles = DatabaseNotification::query()->get()->map(static fn ($row): string => $row->data['title'])->all();

    expect($titles)->toBe(['gcego-media::messages.notifications.processed', 'gcego-media::messages.notifications.failed'])
        ->and(json_encode(DatabaseNotification::query()->pluck('data')))->not->toContain($media->path)
        ->and(DatabaseNotification::query()->value('gcego_tenant'))->toBe('1');
});

it('sends no notifications by default', function (): void {
    $world = world();
    actAs($world['admin']);
    media()->upload(fixtureFile('png', 'quiet.png'));

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('reports a working setup with its warnings, and problems', function (): void {
    $report = media()->inspect();

    expect($report->healthy())->toBeTrue()
        ->and($report->codes())->toContain('queue.sync', 'scanning.none')
        ->and($report->disks['local']['available'])->toBeTrue()
        ->and($report->settings['gd'])->toBeTrue()
        ->and(json_encode($report->toArray()))->not->toContain(sys_get_temp_dir());

    $this->reboot(['gcego-media.disks' => ['local', 'missing'], 'gcego-media.downloads.require_authorization' => false]);

    expect(media()->inspect()->codes())->toContain('disk.missing', 'downloads.signed_only')
        ->and(media()->inspect()->healthy())->toBeFalse();
});

it('reports processing failures', function (): void {
    $world = world();
    actAs($world['admin']);
    media()->upload(fixtureFile('txt', 'broken.txt'))->forceFill(['status' => MediaStatus::Failed])->save();

    expect(media()->inspect()->codes())->toContain('processing.failed')
        ->and(media()->inspect()->statuses)->toBe(['failed' => 1]);
});

it('regenerates variants on demand', function (): void {
    $world = world();
    actAs($world['admin']);
    $media = media()->upload(fixtureFile('png', 'again.png'));

    $this->artisan('gcego:media:regenerate')->expectsOutputToContain('1 media queued')->assertSuccessful();

    expect($media->fresh()?->status)->toBe(MediaStatus::Ready);
});
