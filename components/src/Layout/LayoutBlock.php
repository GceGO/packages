<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the LayoutBlock family. */
abstract class LayoutBlock extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('align', Type::string()),
            new PropertyDefinition('justify', Type::string()),
            new PropertyDefinition('gap', Type::string()),
            new PropertyDefinition('width', Type::string()),
            new PropertyDefinition('height', Type::string()),
            new PropertyDefinition('hidden', Type::bool()),
        );
    }
}
