<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The widget classes: Widgets::make([SubjectsOverview::class, ...]).
 *
 * The classes are never autoloaded when the blueprint is defined.
 */
final readonly class Widgets extends ReferenceCollectionDefinition
{
    public const string Key = 'widgets';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
