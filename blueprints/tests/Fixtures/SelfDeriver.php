<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\Contracts\BlueprintDeriver;
use Gcego\Blueprints\Contracts\BlueprintInterface;

/**
 * Wrongly derives the blueprint itself, which would collide in the registry.
 */
final readonly class SelfDeriver implements BlueprintDeriver
{
    public function derive(BlueprintInterface $blueprint): iterable
    {
        yield $blueprint;
    }
}
