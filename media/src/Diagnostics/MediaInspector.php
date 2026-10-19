<?php

declare(strict_types=1);

namespace Gcego\Media\Diagnostics;

use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\MediaConfig;
use Gcego\Media\Metadata\MediaMetadataExtractor;
use Gcego\Media\Models\MediaRecord;
use Gcego\Media\Support\Keys;
use Gcego\Media\Transformations\ImageTransformer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Checks what media needs, without touching any file: disks (configured,
 * reachable by an existence check, public URLs), upload limits against
 * PHP's, the media tables, routes, the queue, GD, ffprobe, the scanner
 * and processing failures. Never reports file contents, paths or credentials.
 */
final readonly class MediaInspector
{
    public function __construct(
        private MediaConfig $config,
        private Repository $settings,
        private ConnectionResolverInterface $connections,
        private MediaCollectionRegistry $collections,
        private MediaMetadataExtractor $metadata,
        private Router $router,
    ) {}

    public function inspect(): MediaReport
    {
        $diagnostics = [];
        $disks = [];

        foreach ($this->config->disks as $disk) {
            $settings = $this->settings->get('filesystems.disks.'.$disk);
            $available = false;

            if (! is_array($settings)) {
                $diagnostics[] = MediaDiagnostic::error('disk.missing', sprintf('The disk "%s" is not configured in filesystems.disks.', $disk));
            } else {
                try {
                    app('filesystem')->disk($disk)->exists($this->config->pathPrefix.'/.gcego-probe');
                    $available = true;
                } catch (Throwable) {
                    $diagnostics[] = MediaDiagnostic::error('disk.unavailable', sprintf('The disk "%s" cannot be reached.', $disk));
                }
            }

            $disks[$disk] = ['driver' => is_array($settings) && is_string($settings['driver'] ?? null) ? $settings['driver'] : null, 'available' => $available, 'public_url' => is_array($settings) && ($settings['visibility'] ?? null) === 'public'];
        }

        if ($disks[$this->config->disk]['public_url'] ?? false) {
            $diagnostics[] = MediaDiagnostic::warning('disk.default_public', sprintf('The default disk "%s" has public visibility; private media should live on a private disk.', $this->config->disk));
        }

        $php = self::kilobytes((string) ini_get('upload_max_filesize'));
        $post = self::kilobytes((string) ini_get('post_max_size'));

        if ($php !== null && $php < $this->config->maxSize) {
            $diagnostics[] = MediaDiagnostic::warning('uploads.php_limit', sprintf('PHP upload_max_filesize (%d KB) is below gcego-media.uploads.max_size (%d KB).', $php, $this->config->maxSize));
        }

        if ($post !== null && $post < $this->config->maxSize) {
            $diagnostics[] = MediaDiagnostic::warning('uploads.post_limit', sprintf('PHP post_max_size (%d KB) is below gcego-media.uploads.max_size (%d KB).', $post, $this->config->maxSize));
        }

        $connection = $this->connections->connection();
        $schema = $connection instanceof Connection ? $connection->getSchemaBuilder() : null;
        $tables = $schema !== null && $schema->hasTable('gcego_media') && $schema->hasTable('gcego_media_attachments');

        if (! $tables) {
            $diagnostics[] = MediaDiagnostic::error('database.tables', 'The gcego_media tables are missing: run php artisan migrate.');
        }

        foreach (['gcego.media.show', 'gcego.media.download', 'gcego.media.variant'] as $route) {
            if ($this->router->getRoutes()->getByName($route) === null) {
                $diagnostics[] = MediaDiagnostic::error('routes.missing', sprintf('The route "%s" is missing (routes cached before gcego/media was installed?).', $route));
            }
        }

        $queue = $this->config->queueConnection ?? $this->settings->get('queue.default');

        if ($this->config->transformations && $queue === 'sync') {
            $diagnostics[] = MediaDiagnostic::warning('queue.sync', 'Media processing runs on the "sync" queue, inside requests; use a queue worker in production.');
        }

        if (! ImageTransformer::available()) {
            $diagnostics[] = MediaDiagnostic::warning('images.gd', 'The GD extension is missing: no image variants or metadata stripping.');
        }

        $ffprobe = $this->metadata->probes();

        if ($this->config->ffprobe !== null && ! $ffprobe) {
            $diagnostics[] = MediaDiagnostic::warning('metadata.ffprobe', 'ffprobe is not available: audio and video get no duration or dimensions.');
        }

        if ($this->config->scanner === null) {
            $diagnostics[] = MediaDiagnostic::warning('scanning.none', 'No malware scanner is configured: files are "unscanned" (not claimed clean).');
        }

        if (! $this->config->requireAuthorization) {
            $diagnostics[] = MediaDiagnostic::warning('downloads.signed_only', 'Valid signed links are served without authorizing the viewer.');
        }

        if (! $this->config->enforceTenantScope) {
            $diagnostics[] = MediaDiagnostic::warning('tenancy.unscoped', 'Media queries are not tenant-scoped.');
        }

        if (! is_string($this->settings->get('app.key')) || $this->settings->get('app.key') === '') {
            $diagnostics[] = MediaDiagnostic::error('signing.key', 'APP_KEY is missing: signed media URLs and path hashes need it.');
        }

        $statuses = [];

        if ($tables) {
            foreach (MediaRecord::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $count) {
                $statuses[(string) $status] = Keys::int($count);
            }

            if (($statuses['failed'] ?? 0) > 0) {
                $diagnostics[] = MediaDiagnostic::warning('processing.failed', sprintf('%d media failed processing (php artisan gcego:media:regenerate --failed).', $statuses['failed']));
            }
        }

        return new MediaReport(
            disks: $disks,
            settings: [
                'default_disk' => $this->config->disk,
                'visibility' => $this->config->visibility->value,
                'max_size_kb' => $this->config->maxSize,
                'max_files' => $this->config->maxFiles,
                'allowed_types' => count($this->config->allowedTypes),
                'temporary_minutes' => $this->config->temporaryMinutes,
                'queue' => is_string($queue) ? $queue : null,
                'gd' => ImageTransformer::available(),
                'ffprobe' => $ffprobe,
                'scanner' => $this->config->scanner !== null,
                'signed_minutes' => $this->config->signedMinutes,
                'tenant_scope' => $this->config->enforceTenantScope,
                'deny_executables' => $this->config->denyExecutables,
            ],
            statuses: $statuses,
            collections: array_keys($this->collections->all()),
            diagnostics: $diagnostics,
        );
    }

    private static function kilobytes(string $value): ?int
    {
        if (preg_match('/^(\d+)\s*([KMG]?)$/i', trim($value), $match) !== 1) {
            return null;
        }

        $number = (int) $match[1];

        return match (strtoupper($match[2])) {
            'G' => $number * 1024 * 1024,
            'M' => $number * 1024,
            'K' => $number,
            default => intdiv($number, 1024),
        };
    }
}
