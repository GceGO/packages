<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Gcego\Core\Bootstrap\BootstrapContext;
use Gcego\Core\Contracts\Bootable;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Packages\PackageDescriptor;
use Illuminate\Support\ServiceProvider;

/**
 * Simulates a future gcego/tables package: an ordinary Laravel provider that
 * implements PackageInterface and registers an extension.
 */
final class TablesServiceProvider extends ServiceProvider implements Bootable, PackageInterface
{
    public function register(): void
    {
        $this->app->singletonIf(BootLog::class);

        $this->app->make(ExtensionManager::class)->register(
            FixtureExtension::make('tables.export', capabilities: ['tables.export'], log: $this->app->make(BootLog::class)),
        );
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor('gcego/tables', version: '1.0.0', requires: ['gcego/core'], capabilities: ['tables']);
    }

    public function bootPackage(BootstrapContext $context): void
    {
        $context->app->make(BootLog::class)->record('package:gcego/tables');
    }
}
