<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

abstract class NumericField extends FieldComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('value', Type::float(), rule: self::finite(...), ruleDescription: 'finite number'),
            new PropertyDefinition('min', Type::float(), rule: self::finite(...), ruleDescription: 'finite number'),
            new PropertyDefinition('max', Type::float(), rule: self::finite(...), ruleDescription: 'finite number'),
            new PropertyDefinition('step', Type::float(), rule: static fn (mixed $value): bool => self::finite($value) && $value > 0, ruleDescription: 'positive finite number'),
        )->withConstraint(static function (ComponentInterface $component): ?string {
            $min = $component->get('min');
            $max = $component->get('max');

            return self::finite($min) && self::finite($max) && $min > $max ? 'min must not exceed max.' : null;
        });
    }

    private static function finite(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }
}
