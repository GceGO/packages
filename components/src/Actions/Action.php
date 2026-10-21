<?php

declare(strict_types=1);

namespace Gcego\Components\Actions;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the Action family. */
abstract class Action extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('url', Type::string()),
            new PropertyDefinition('icon', Type::string()),
            new PropertyDefinition('variant', Type::string()),
            new PropertyDefinition('color', Type::string()),
            new PropertyDefinition('disabled', Type::bool()),
            new PropertyDefinition('loading', Type::bool()),
        );
    }
}
