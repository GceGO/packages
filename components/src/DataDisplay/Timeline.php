<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;

class Timeline extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.timeline');
    }
}
