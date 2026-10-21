<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Schema\ComponentDefinition;

class EmptyState extends Message
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('feedback.emptyState');
    }
}
