<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The form definition class: Form::make(SubjectForm::class).
 *
 * The class is never autoloaded when the blueprint is defined.
 */
final readonly class Form extends ReferenceDefinition
{
    public const string Key = 'form';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
