<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Map extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.map')
            ->withProperties(
                new PropertyDefinition('latitude', Type::float(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= -90 && $value <= 90, ruleDescription: 'finite number between -90 and 90'),
                new PropertyDefinition('longitude', Type::float(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= -180 && $value <= 180, ruleDescription: 'finite number between -180 and 180'),
                new PropertyDefinition('zoom', Type::float(), rule: static fn (mixed $value): bool => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value <= 24, ruleDescription: 'finite number between 0 and 24'),
            )
            ->withoutChildren();
    }
}
