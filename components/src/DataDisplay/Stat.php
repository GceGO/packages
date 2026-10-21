<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Stat extends TextualComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.stat')
            ->withProperties(
                new PropertyDefinition('value', Type::string()),
                new PropertyDefinition('description', Type::string()),
                new PropertyDefinition('icon', Type::string()),
            );
    }
}
