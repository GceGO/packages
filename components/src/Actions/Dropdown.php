<?php

declare(strict_types=1);

namespace Gcego\Components\Actions;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Dropdown extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('actions.dropdown')
            ->withProperties(
                new PropertyDefinition('trigger', Type::string()),
            );
    }
}
