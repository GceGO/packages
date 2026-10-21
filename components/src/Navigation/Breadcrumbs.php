<?php

declare(strict_types=1);

namespace Gcego\Components\Navigation;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;

class Breadcrumbs extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('navigation.breadcrumbs');
    }
}
