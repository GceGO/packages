<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures\Extensions;

use Gcego\Blueprints\Definitions\IdentifierListDefinition;

/**
 * The panels a feature appears in, as gcego/panels would contribute it: Panels::make(['admin']).
 */
final readonly class Panels extends IdentifierListDefinition
{
    public const string Key = 'panels';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
