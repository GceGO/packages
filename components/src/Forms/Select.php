<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Select extends ChoiceField
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('forms.select')
            ->withProperties(
                new PropertyDefinition('value', Type::string()),
            );
    }
}
