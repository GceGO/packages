<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Facades;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Resolution\RegistryBlueprintResolver;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool has(string $name)
 * @method static BlueprintInterface resolve(string $name)
 * @method static list<string> names()
 * @method static array<string, BlueprintInterface> all()
 *
 * @see RegistryBlueprintResolver
 */
final class Blueprints extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BlueprintResolver::class;
    }
}
