<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The Eloquent model (or other record class) the feature manages: Model::make(Subject::class).
 *
 * The class is never autoloaded when the blueprint is defined.
 */
final readonly class Model extends ReferenceDefinition
{
    public const string Key = 'model';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
