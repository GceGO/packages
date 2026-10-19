<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

use OutOfBoundsException;

/**
 * Looks blueprints up by name.
 *
 * BlueprintCollection implements it in memory. gcego/registry is expected to
 * provide the discovering, application-wide implementation, so runtimes can
 * depend on this contract alone.
 */
interface BlueprintResolver
{
    public function has(string $name): bool;

    /**
     * @throws OutOfBoundsException when no blueprint has the name.
     */
    public function resolve(string $name): BlueprintInterface;

    /**
     * @return list<string>
     */
    public function names(): array;
}
