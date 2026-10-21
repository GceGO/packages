<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the TitledBlock family. */
abstract class TitledBlock extends LayoutBlock
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('title', Type::string()),
            new PropertyDefinition('icon', Type::string()),
            new PropertyDefinition('disabled', Type::bool()),
        );
    }
}
