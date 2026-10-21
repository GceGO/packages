<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Calendar extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.calendar')
            ->withProperties(
                new PropertyDefinition('events', Type::array()),
                new PropertyDefinition('view', Type::string()),
                new PropertyDefinition('timezone', Type::string()),
            )
            ->withoutChildren();
    }
}
