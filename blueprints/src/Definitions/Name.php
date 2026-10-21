<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\Naming;

/**
 * The blueprint's logical identity, required: Name::make('subjects').
 *
 * Names are lowercase identifiers (letters, digits, "-" and "_"; at most
 * 100 characters). They are not silently rewritten: "Subjects" is rejected
 * with a hint rather than lowercased, so one blueprint has one spelling.
 */
final readonly class Name extends TextDefinition
{
    public const string Key = 'name';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    protected static function normalize(string $value): string
    {
        if (Naming::isName($value)) {
            return $value;
        }

        if ($value === '') {
            throw InvalidDefinitionException::because(self::Key, 'the name must not be empty.', 'Use an identifier such as "subjects".');
        }

        $suggestion = Naming::slugify($value);

        throw InvalidDefinitionException::because(
            self::Key,
            sprintf('"%s" is not a lowercase identifier of at most %d characters (letters, digits, "-" and "_", starting with a letter).', $value, Naming::MaxNameLength),
            Naming::isName($suggestion) ? sprintf('Did you mean "%s"?', $suggestion) : null,
        );
    }
}
