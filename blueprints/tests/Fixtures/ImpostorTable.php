<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\Contracts\BlueprintDefinition;

/**
 * Claims the built-in "table" key without being the Table definition.
 */
final readonly class ImpostorTable implements BlueprintDefinition
{
    public static function definitionKey(): string
    {
        return 'table';
    }

    public function key(): string
    {
        return 'table';
    }

    public function value(): string
    {
        return 'anything';
    }
}
