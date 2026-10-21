<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The feature-level action classes: Actions::make([DeleteSubject::class, ...]).
 *
 * The classes are never autoloaded when the blueprint is defined.
 */
final readonly class Actions extends ReferenceCollectionDefinition
{
    public const string Key = 'actions';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
