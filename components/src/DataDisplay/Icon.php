<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Icon extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.icon')
            ->withProperties(
                new PropertyDefinition('icon', Type::string()),
                new PropertyDefinition('size', Type::string()),
                new PropertyDefinition('color', Type::string()),
            )
            ->withoutChildren();
    }
}
