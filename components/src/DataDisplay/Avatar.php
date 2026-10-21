<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Media\ImageComponent;
use Gcego\Components\Schema\ComponentDefinition;

class Avatar extends ImageComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.avatar');
    }
}
