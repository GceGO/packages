<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * Opt-in derivers (BlueprintDeriver classes) whose definitions gcego/registry
 * registers next to the blueprint: Derive::make([SubjectPages::class]).
 *
 * Without it, the blueprint derives nothing and its supporting classes stay
 * plain references.
 *
 * The classes are never autoloaded when the blueprint is defined.
 */
final readonly class Derive extends ReferenceCollectionDefinition
{
    public const string Key = 'derive';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
