<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Progress extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.progress')
            ->withProperties(
                new PropertyDefinition('value', Type::float(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0, ruleDescription: 'non-negative finite number'),
                new PropertyDefinition('max', Type::float(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0, ruleDescription: 'non-negative finite number'),
            )
            ->withoutChildren();
    }
}
