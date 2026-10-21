<?php

declare(strict_types=1);

namespace Gcego\Modules\Tests\Fixtures\Extensions;

use Gcego\Components\Properties\Property;
use Gcego\Modules\Contracts\ModuleExtension;
use Gcego\Modules\Module;

/**
 * An extension that (wrongly) renames a module.
 */
final class RenameModule implements ModuleExtension
{
    public function extends(string $module): bool
    {
        return true;
    }

    public function extend(Module $module): Module
    {
        return $module->withProperty(new Property(Module::Name, 'renamed'));
    }
}
