<?php

declare(strict_types=1);

namespace Gcego\Components\Actions;

use Gcego\Components\Schema\ComponentDefinition;

class DropdownItem extends Action
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('actions.dropdownItem');
    }
}
