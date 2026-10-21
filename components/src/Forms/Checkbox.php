<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Checkbox extends FieldComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('forms.checkbox')
            ->withProperties(
                new PropertyDefinition('checked', Type::bool()),
            );
    }
}
