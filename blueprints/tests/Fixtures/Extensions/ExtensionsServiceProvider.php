<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\DefinitionCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the extension definitions the way their owning packages would:
 * in register(), before any blueprint is defined.
 */
final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        DefinitionCatalog::register(Navigation::class);
        DefinitionCatalog::register(Permissions::class);
        DefinitionCatalog::register(Panels::class);
        DefinitionCatalog::register(Theme::class);
    }
}
