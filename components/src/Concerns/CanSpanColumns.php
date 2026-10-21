<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Support\Responsive;

/**
 * columnSpan(), columnSpanFull() and columnStart(), optionally per breakpoint.
 */
final class CanSpanColumns implements Concern
{
    public static function properties(): array
    {
        $positive = static fn (mixed $value): bool => is_int($value) && $value > 0;

        return [
            new PropertyDefinition(
                'columnSpan',
                [Type::int(), Type::string(), Type::array()],
                rule: static fn (mixed $value): bool => Responsive::accepts($value, static fn (mixed $item): bool => $positive($item) || $item === 'full'),
                ruleDescription: Responsive::describe('positive integer or "full"'),
            ),
            new PropertyDefinition(
                'columnStart',
                [Type::int(), Type::array()],
                rule: static fn (mixed $value): bool => Responsive::accepts($value, $positive),
                ruleDescription: Responsive::describe('positive integer'),
            ),
        ];
    }
}
