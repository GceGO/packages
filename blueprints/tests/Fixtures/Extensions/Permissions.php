<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\IdentifierListDefinition;

/**
 * The abilities a feature exposes, as gcego/security would contribute it:
 * Permissions::make(['view', 'create', 'subjects.export']).
 */
final readonly class Permissions extends IdentifierListDefinition
{
    public const string Key = 'permissions';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
