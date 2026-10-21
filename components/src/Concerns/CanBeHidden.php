<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * hidden(), visible(), hiddenOn() and visibleOn(); read with Component::isHiddenIn().
 */
final class CanBeHidden implements Concern
{
    public static function properties(): array
    {
        $operations = static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $operation): bool => ! is_string($operation) || $operation === '') === [];

        return [
            new PropertyDefinition('hidden', Type::bool()),
            new PropertyDefinition('visible', Type::bool()),
            new PropertyDefinition('hiddenOn', Type::list(), rule: $operations, ruleDescription: 'non-empty list of operation names'),
            new PropertyDefinition('visibleOn', Type::list(), rule: $operations, ruleDescription: 'non-empty list of operation names'),
        ];
    }
}
