<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;

class Item extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('item')
            ->withoutChildren();
    }
}
