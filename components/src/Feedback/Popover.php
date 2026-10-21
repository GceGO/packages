<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Schema\ComponentDefinition;

class Popover extends Message
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('feedback.popover');
    }
}
