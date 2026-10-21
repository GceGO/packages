<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * statePath('address'): nests the state of every descendant under that path.
 */
final class HasStatePath implements Concern
{
    public const string Pattern = '/\A[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*\z/';

    public static function properties(): array
    {
        return [
            new PropertyDefinition(
                'statePath',
                Type::string(),
                rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::Pattern, $value) === 1,
                ruleDescription: 'dot-separated path segments of letters, digits, "_" or "-"',
            ),
        ];
    }
}
