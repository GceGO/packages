<?php

declare(strict_types=1);

namespace Gcego\Media\Storage;

use Gcego\Media\MediaConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

/**
 * Storage paths of media:
 *
 *     <prefix>/<tenant hash>/<collection>/<yyyy>/<mm>/<ulid>/<random>.<ext>
 *     <prefix>/<tenant hash>/<collection>/<yyyy>/<mm>/<ulid>/variants/<name>.<ext>
 *
 * Every segment is generated: the original filename never reaches a path,
 * the tenant appears only as a keyed hash ("shared" without a tenant), and
 * the extension comes from the detected content type.
 */
final readonly class StoragePathResolver
{
    public function __construct(private MediaConfig $config, private Repository $settings) {}

    public function directory(string $ulid, string $collection, ?string $tenant, ?\DateTimeInterface $at = null): string
    {
        $at ??= now();

        return implode('/', [$this->config->pathPrefix, $this->tenantSegment($tenant), $collection, $at->format('Y'), $at->format('m'), strtolower($ulid)]);
    }

    public function original(string $directory, string $extension): string
    {
        return $directory.'/'.Str::lower(Str::random(32)).'.'.$extension;
    }

    public function variant(string $originalPath, string $variant, string $extension): string
    {
        return dirname($originalPath).'/variants/'.$variant.'.'.$extension;
    }

    public function tenantSegment(?string $tenant): string
    {
        if ($tenant === null) {
            return 'shared';
        }

        $key = $this->settings->get('app.key');

        return 't'.substr(hash_hmac('sha256', 'gcego-media|'.$tenant, is_string($key) ? $key : ''), 0, 24);
    }

    /**
     * Whether a path is one gcego/media generated (for cleanup and safety checks).
     */
    public function isManaged(string $path): bool
    {
        return str_starts_with($path, $this->config->pathPrefix.'/') && ! str_contains($path, '..') && preg_match('#^[a-z0-9/_.-]+$#', $path) === 1;
    }
}
