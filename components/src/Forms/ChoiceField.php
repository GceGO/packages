<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the ChoiceField family. */
abstract class ChoiceField extends FieldComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('options', Type::array()),
            new PropertyDefinition('searchable', Type::bool()),
        );
    }
}
