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
 * Simulates a future gcego/forms package that integrates with tables when present.
 *
 * Registered before TablesServiceProvider in tests to prove that GCEGO boot
 * order follows dependencies, not Laravel's provider order.
 */
final class FormsServiceProvider extends ServiceProvider implements Bootable, PackageInterface
{
    public function register(): void
    {
        $this->app->singletonIf(BootLog::class);

        $this->app->make(ExtensionManager::class)->register(
            FixtureExtension::make('forms.table-filters', whenPackages: ['gcego/tables'], log: $this->app->make(BootLog::class)),
        );
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor('gcego/forms', version: '1.0.0', requires: ['gcego/core'], optional: ['gcego/tables'], capabilities: ['forms']);
    }

    public function bootPackage(BootstrapContext $context): void
    {
        $context->app->make(BootLog::class)->record('package:gcego/forms');
    }
}
