<?php

declare(strict_types=1);

namespace Gcego\Components\Concerns;

use Gcego\Components\Contracts\Concern;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * extraAttributes(['data-test' => 'name']), merged with extraAttributes([...], merge: true).
 */
final class HasExtraAttributes implements Concern
{
    public static function properties(): array
    {
        return [
            new PropertyDefinition(
                'extraAttributes',
                Type::array(),
                rule: static fn (mixed $value): bool => is_array($value) && array_filter(
                    $value,
                    static fn (mixed $attribute, int|string $name): bool => ! is_string($name) || preg_match('/\A[A-Za-z_:@][A-Za-z0-9_:.\-]*\z/', $name) !== 1 || ($attribute !== null && ! is_scalar($attribute)),
                    ARRAY_FILTER_USE_BOTH,
                ) === [],
                ruleDescription: 'attribute names mapped to scalar or null values',
            ),
        ];
    }
}
