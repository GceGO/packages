<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\Naming;

/**
 * The feature's icon identifier: Icon::make('academic-cap'), Icon::make('lucide:book-open').
 *
 * Lowercase letters, digits and "." "_" ":" "/" "-", at most 100 characters.
 * Which icon sets exist is the renderer's concern.
 */
final readonly class Icon extends TextDefinition
{
    public const string Key = 'icon';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    protected static function normalize(string $value): string
    {
        if (! Naming::isIcon($value)) {
            throw InvalidDefinitionException::because(self::Key, sprintf('"%s" is not an icon identifier such as "academic-cap" or "lucide:book-open".', $value));
        }

        return $value;
    }
}
