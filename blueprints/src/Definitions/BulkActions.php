<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * Feature-level actions that run on a selection of records:
 * BulkActions::make([DeleteSubjects::class, ExportSubjects::class]).
 *
 * Kept apart from Actions because they take several records, so the table
 * engine offers them on selections only. The classes are never autoloaded
 * when the blueprint is defined.
 */
final readonly class BulkActions extends ReferenceCollectionDefinition
{
    public const string Key = 'bulk_actions';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
