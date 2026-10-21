<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The relation manager classes: Relations::make([TopicsRelation::class, ...]).
 *
 * The classes are never autoloaded when the blueprint is defined.
 */
final readonly class Relations extends ReferenceCollectionDefinition
{
    public const string Key = 'relations';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
