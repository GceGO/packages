<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Support\Responsive;

/**
 * columns(2) or columns(['default' => 1, 'lg' => 3]) for components that lay out children.
 */
final class HasColumns implements Concern
{
    public static function properties(): array
    {
        return [
            new PropertyDefinition(
                'columns',
                [Type::int(), Type::array()],
                rule: static fn (mixed $value): bool => Responsive::accepts($value, static fn (mixed $item): bool => is_int($item) && $item > 0),
                ruleDescription: Responsive::describe('positive integer'),
            ),
        ];
    }
}
