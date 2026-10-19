<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Gcego\Luminaries\LuminariesServiceProvider;
use Livewire\LivewireServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,
            LuminariesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('luminaries.missing.behavior', 'exception');
        $app['config']->set('luminaries.security.trusted_roots', [dirname(__DIR__).'/tests/Fixtures']);
        $app['config']->set('luminaries.collections', [
            'custom' => ['path' => dirname(__DIR__).'/tests/Fixtures/icons/trusted', 'label' => 'Fixture icons'],
            'unsafe' => ['path' => dirname(__DIR__).'/tests/Fixtures/icons/unsafe', 'label' => 'Unsafe fixtures'],
        ]);
        $app['config']->set('luminaries.cache.path', sys_get_temp_dir().'/gcego-luminaries-test-'.getmypid().'.php');
    }
}
