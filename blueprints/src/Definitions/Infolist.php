<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The infolist definition class: Infolist::make(SubjectInfolist::class).
 *
 * The class is never autoloaded when the blueprint is defined.
 */
final readonly class Infolist extends ReferenceDefinition
{
    public const string Key = 'infolist';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
