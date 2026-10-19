<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Caching;

use RuntimeException;

/**
 * The cached list of icons per Blade Icons provider, written by
 * gcego:icons:cache. It replaces filesystem checks and directory scans with
 * array lookups. A manifest whose configuration fingerprint differs from the
 * current one is ignored, so stale caches never change resolution.
 *
 * It only contains package metadata (provider and icon names), never
 * request, user or tenant data.
 */
final class IconManifest
{
    /**
     * @var array{fingerprint: string, generated_at: string, providers: array<string, array<string, true>>}|false|null
     */
    private array|false|null $data = null;

    public function __construct(
        private readonly ?string $path,
        private readonly string $fingerprint,
    ) {}

    public function path(): ?string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return $this->path !== null && is_file($this->path);
    }

    public function isFresh(): bool
    {
        return $this->load() !== null;
    }

    /**
     * Whether the manifest lists an icon: null when it does not cover the provider (or is missing or stale).
     */
    public function has(string $provider, string $icon): ?bool
    {
        $data = $this->load();

        if ($data === null || ! isset($data['providers'][$provider])) {
            return null;
        }

        return isset($data['providers'][$provider][$icon]);
    }

    /**
     * @return list<string>|null
     */
    public function icons(string $provider): ?array
    {
        $data = $this->load();

        if ($data === null || ! isset($data['providers'][$provider])) {
            return null;
        }

        return array_map(strval(...), array_keys($data['providers'][$provider]));
    }

    public function generatedAt(): ?string
    {
        return $this->load()['generated_at'] ?? null;
    }

    /**
     * @param  array<string, list<string>>  $providers
     */
    public function write(array $providers): void
    {
        if ($this->path === null) {
            throw new RuntimeException('GCEGO Luminaries: luminaries.cache.path is not configured.');
        }

        $directory = dirname($this->path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException(sprintf('GCEGO Luminaries: the directory %s must exist and be writable.', $directory));
        }

        $data = ['fingerprint' => $this->fingerprint, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'), 'providers' => []];

        foreach ($providers as $provider => $icons) {
            $data['providers'][$provider] = array_fill_keys($icons, true);
        }

        $temporary = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, '<?php return '.var_export($data, true).';'.PHP_EOL, LOCK_EX);
        rename($temporary, $this->path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }

        $this->data = $data;
    }

    public function clear(): bool
    {
        $this->data = false;

        return $this->path !== null && is_file($this->path) && unlink($this->path);
    }

    /**
     * @return array{fingerprint: string, generated_at: string, providers: array<string, array<string, true>>}|null
     */
    private function load(): ?array
    {
        if ($this->data === null) {
            $this->data = false;

            if ($this->path !== null && is_file($this->path)) {
                $data = require $this->path;

                if (is_array($data) && ($data['fingerprint'] ?? null) === $this->fingerprint && is_array($data['providers'] ?? null)) {
                    /** @var array{fingerprint: string, generated_at: string, providers: array<string, array<string, true>>} $data */
                    $this->data = $data;
                }
            }
        }

        return $this->data === false ? null : $this->data;
    }
}
