<?php

declare(strict_types=1);

namespace Gcego\Components\Actions;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;

class ButtonGroup extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('actions.buttonGroup');
    }
}
