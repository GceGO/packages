<?php

declare(strict_types=1);

namespace Gcego\Modules\Building;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Exceptions\InvalidBuilderException;

/**
 * The fluent form of a Module:
 *
 *     Module::define('people')
 *         ->title('People')
 *         ->navigation()
 *         ->schema([DashboardPage::define(...)])
 *         ->resourcesIn(__DIR__.'/Resources')
 */
class ModuleBuilder extends ComponentBuilder
{
    /**
     * Add the resource each PHP file of the directory returns (in file name order).
     */
    public function resourcesIn(string $directory): static
    {
        $resources = [];

        foreach (glob(rtrim($directory, '/').'/*.php') ?: [] as $file) {
            $resource = (static fn (string $__gcegoResourceFile): mixed => require $__gcegoResourceFile)($file);

            if (! is_object($resource)) {
                throw InvalidBuilderException::invalidValue($this->component, 'resourcesIn', sprintf('%s must return a resource.', basename($file)));
            }

            $resources[] = $resource;
        }

        return $this->schema($resources);
    }
}
