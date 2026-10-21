<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the Message family. */
abstract class Message extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('title', Type::string()),
            new PropertyDefinition('message', Type::string()),
            new PropertyDefinition('status', Type::string()),
            new PropertyDefinition('icon', Type::string()),
        );
    }
}
