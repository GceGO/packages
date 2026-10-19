<?php

declare(strict_types=1);

use Gcego\Luminaries\Caching\IconManifest;
use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\LuminariesConfig;
use Gcego\Luminaries\Registry\IconRegistry;
use Gcego\Luminaries\Rendering\IconRenderer;
use Gcego\Luminaries\Security\PathGuard;
use Gcego\Luminaries\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Integration');

function fixtures(string $path = ''): string
{
    return __DIR__.'/Fixtures'.($path === '' ? '' : '/'.$path);
}

/**
 * Apply luminaries configuration and rebuild the Luminaries singletons from it.
 *
 * @param  array<string, mixed>  $config
 */
function configureLuminaries(array $config): void
{
    foreach ($config as $key => $value) {
        config()->set("luminaries.{$key}", $value);
    }

    foreach ([
        LuminariesConfig::class,
        PathGuard::class,
        IconRenderer::class,
        IconManifest::class,
        IconRegistry::class,
        Luminaries::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }

    Gcego\Luminaries\Facades\Luminaries::clearResolvedInstances();
}

function rootCause(Throwable $exception): Throwable
{
    while ($exception->getPrevious() !== null) {
        $exception = $exception->getPrevious();
    }

    return $exception;
}
