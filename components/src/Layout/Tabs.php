<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Tabs extends LayoutBlock
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('layout.tabs')
            ->withProperties(
                new PropertyDefinition('active', Type::string()),
            );
    }
}
