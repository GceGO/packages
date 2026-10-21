<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The page classes, in navigation order: Pages::make([ListSubjects::class, ...]).
 *
 * The classes are never autoloaded when the blueprint is defined.
 */
final readonly class Pages extends ReferenceCollectionDefinition
{
    public const string Key = 'pages';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
