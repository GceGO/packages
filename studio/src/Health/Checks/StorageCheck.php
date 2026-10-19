<?php

declare(strict_types=1);

namespace Gcego\Studio\Health\Checks;

use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel's cache directories (bootstrap/cache, storage/framework/{cache,views,sessions}) are writable.
 */
final readonly class StorageCheck implements StudioHealthCheck
{
    public function __construct(private Application $app) {}

    public function id(): string
    {
        return 'storage.writable';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $paths = [$this->app->bootstrapPath('cache'), $this->app->storagePath('framework/cache'), $this->app->storagePath('framework/views'), $this->app->storagePath('framework/sessions')];
        $unwritable = array_values(array_filter($paths, static fn (string $path): bool => ! is_dir($path) || ! is_writable($path)));

        return $unwritable === []
            ? HealthCheckResult::pass($this->id(), 'Writable directories', 'The cache directories are writable.', 'gcego/studio')
            : HealthCheckResult::failure($this->id(), 'Writable directories', sprintf('%d cache directories are missing or not writable.', count($unwritable)), 'Create them and give the web server user write access (bootstrap/cache, storage/framework/*).', 'gcego/studio', ['paths' => array_map(fn (string $path): string => $this->relative($path), $unwritable)]);
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace($this->app->basePath(), '', $path), '/');
    }
}
