<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The table definition class: Table::make(SubjectsTable::class).
 *
 * The class is never autoloaded when the blueprint is defined.
 */
final readonly class Table extends ReferenceDefinition
{
    public const string Key = 'table';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
