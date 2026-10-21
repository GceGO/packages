<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Registry;

use Gcego\Blueprints\Contracts\BlueprintInterface;

/**
 * Implements DefinitionProvider::definitions() for a BlueprintProvider: the
 * provider declares exactly one definition, its blueprint.
 *
 * @phpstan-require-implements BlueprintProvider
 */
trait ProvidesBlueprint
{
    abstract public function blueprint(): BlueprintInterface;

    /**
     * @return iterable<BlueprintInterface>
     */
    public function definitions(): iterable
    {
        yield $this->blueprint();
    }
}
