<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\Naming;

/**
 * The display title: Title::make('Subject Management'). When omitted, the
 * blueprint's title is derived from its name.
 */
final readonly class Title extends TextDefinition
{
    public const string Key = 'title';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    protected static function normalize(string $value): string
    {
        if (! Naming::isTitle($value)) {
            throw InvalidDefinitionException::because(self::Key, sprintf('expected a non-empty title of at most %d characters.', Naming::MaxTitleLength));
        }

        return $value;
    }
}
