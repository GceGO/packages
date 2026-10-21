<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the TemporalField family. */
abstract class TemporalField extends FieldComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('value', Type::string()),
            new PropertyDefinition('min', Type::string()),
            new PropertyDefinition('max', Type::string()),
            new PropertyDefinition('format', Type::string()),
            new PropertyDefinition('timezone', Type::string()),
        );
    }
}
