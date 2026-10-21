<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Schema\ComponentDefinition;

class Modal extends Overlay
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('layout.modal');
    }
}
