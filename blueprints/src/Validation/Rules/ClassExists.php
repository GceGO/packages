<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation\Rules;

use Gcego\Blueprints\Validation\BlueprintErrorCode;
use Gcego\Blueprints\Validation\ReferenceRule;
use Throwable;

/**
 * Every referenced class, interface or enum must be autoloadable.
 *
 * This is the only rule that autoloads referenced classes; it never
 * instantiates them. A class whose file fails to load (for example because
 * it extends a class of a package that is not installed) is reported, not thrown.
 */
final readonly class ClassExists implements ReferenceRule
{
    public function appliesTo(string $key): bool
    {
        return true;
    }

    public function check(string $key, string $class): ?string
    {
        try {
            return class_exists($class) || interface_exists($class) || enum_exists($class)
                ? null
                : sprintf('%s does not exist or cannot be autoloaded.', $class);
        } catch (Throwable $exception) {
            // Typically a class extending or implementing something from a package that is not installed.
            return sprintf('%s cannot be autoloaded: %s', $class, $exception->getMessage());
        }
    }

    public function code(): string
    {
        return BlueprintErrorCode::UnresolvableReference->value;
    }
}
