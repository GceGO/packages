<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use Illuminate\Support\Str;

/**
 * The visibility and label rules shared by Component and the live engine.
 */
final class Presentation
{
    /**
     * Hidden by hidden, visible: false, hiddenOn for the operation, or
     * visibleOn for any other operation.
     *
     * @param  array<string, mixed>  $properties  Evaluated properties.
     */
    public static function isHidden(array $properties, ?string $operation): bool
    {
        $hiddenOn = $properties['hiddenOn'] ?? null;
        $visibleOn = $properties['visibleOn'] ?? null;

        return ($properties['hidden'] ?? null) === true
            || ($properties['visible'] ?? null) === false
            || ($operation !== null && is_array($hiddenOn) && in_array($operation, $hiddenOn, true))
            || (is_array($visibleOn) && ! in_array($operation, $visibleOn, true));
    }

    /**
     * "billing.first_name" and "firstName" read "First name".
     */
    public static function labelFromName(string $name): string
    {
        return Str::of($name)->afterLast('.')->kebab()->replace(['-', '_'], ' ')->squish()->ucfirst()->toString();
    }

    public static function label(mixed $label, ?string $name): ?string
    {
        if (is_string($label) && $label !== '') {
            return $label;
        }

        return $name === null ? null : self::labelFromName($name);
    }
}
