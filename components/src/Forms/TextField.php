<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

abstract class TextField extends FieldComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('value', Type::string()),
            new PropertyDefinition('minLength', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'non-negative integer'),
            new PropertyDefinition('maxLength', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'non-negative integer'),
        )->withConstraint(static function (ComponentInterface $component): ?string {
            $min = $component->get('minLength');
            $max = $component->get('maxLength');

            return is_int($min) && is_int($max) && $min > $max ? 'minLength must not exceed maxLength.' : null;
        });
    }
}
