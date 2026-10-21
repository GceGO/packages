<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Chart extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.chart')
            ->withProperties(
                new PropertyDefinition('chartType', Type::string()),
                new PropertyDefinition('data', Type::array()),
                new PropertyDefinition('options', Type::array()),
            )
            ->withoutChildren();
    }
}
