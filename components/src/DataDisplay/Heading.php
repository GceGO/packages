<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Heading extends TextualComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.heading')
            ->withProperties(
                new PropertyDefinition('level', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 6, ruleDescription: 'heading level between 1 and 6'),
            );
    }
}
