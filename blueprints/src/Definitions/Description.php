<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\Naming;

/**
 * Descriptive text: Description::make('Manage academic subjects').
 *
 * Non-empty after trimming, at most 1000 characters; line breaks are allowed,
 * other control characters are not.
 */
final readonly class Description extends TextDefinition
{
    public const string Key = 'description';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    protected static function normalize(string $value): string
    {
        if (! Naming::isDescription($value)) {
            throw InvalidDefinitionException::because(self::Key, sprintf('expected non-empty text of at most %d characters without control characters.', Naming::MaxDescriptionLength));
        }

        return $value;
    }
}
