<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Schema\ComponentDefinition;

class Badge extends TextualComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.badge');
    }
}
