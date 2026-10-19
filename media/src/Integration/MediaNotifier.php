<?php

declare(strict_types=1);

namespace Gcego\Media\Integration;

use Gcego\Media\MediaConfig;
use Gcego\Media\Models\MediaRecord;
use Gcego\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Optional gcego/notifications messages to the uploader when processing
 * finishes or fails (gcego-media.notifications.processing; off by
 * default to avoid noise). They carry the file's display name and
 * nothing of its content, in the media's tenant.
 */
final readonly class MediaNotifier
{
    public function __construct(private MediaConfig $config) {}

    public function processed(MediaRecord $media): void
    {
        $this->send($media, 'gcego-media::messages.notifications.processed', false);
    }

    public function failed(MediaRecord $media): void
    {
        $this->send($media, 'gcego-media::messages.notifications.failed', true);
    }

    private function send(MediaRecord $media, string $title, bool $failure): void
    {
        if (! $this->config->notifyProcessing || ! class_exists(Notification::class)) {
            return;
        }

        $uploader = $media->uploader;

        if (! $uploader instanceof Model || ! method_exists($uploader, 'routeNotificationFor')) {
            return;
        }

        try {
            $builder = Notification::make()->title($title)->translate(['name' => $media->original_name])->category('system')->database()->idempotencyKey('media.'.$media->ulid.'.'.($failure ? 'failed' : 'processed').'.'.($media->processed_at instanceof \DateTimeInterface ? $media->processed_at->getTimestamp() : 0));
            $builder = $failure ? $builder->error() : $builder->success();
            $builder = $media->tenant === null ? $builder->global() : $builder->tenant($media->tenant);
            $builder->sendTo($uploader);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
