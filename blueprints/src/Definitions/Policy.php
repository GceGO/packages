<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

/**
 * The authorization policy of the feature's records: Policy::make(SubjectPolicy::class).
 *
 * Optional: without it, engines use the policy Laravel's Gate resolves for
 * the Model. The class is never autoloaded when the blueprint is defined.
 */
final readonly class Policy extends ReferenceDefinition
{
    public const string Key = 'policy';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
