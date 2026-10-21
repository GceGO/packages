<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class TimelineItem extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.timelineItem')
            ->withProperties(
                new PropertyDefinition('title', Type::string()),
                new PropertyDefinition('date', Type::string()),
                new PropertyDefinition('icon', Type::string()),
            );
    }
}
