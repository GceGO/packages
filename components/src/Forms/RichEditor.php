<?php

declare(strict_types=1);

namespace Gcego\Components\Forms;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class RichEditor extends TextField
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('forms.richEditor')
            ->withProperties(
                new PropertyDefinition('rows', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
            );
    }
}
