<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Toast extends DismissibleMessage
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('feedback.toast')
            ->withProperties(
                new PropertyDefinition('duration', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'non-negative integer'),
            );
    }
}
