<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

/**
 * Builds further registry definitions from a blueprint, such as one page
 * definition per entry of Pages::make([...]).
 *
 * Derivers run only for blueprints that request them explicitly with
 * Derive::make([...]). They are instantiated without arguments and must be
 * pure and deterministic (no queries, no routes, no I/O), as gcego/registry
 * requires of derived definitions.
 */
interface BlueprintDeriver
{
    /**
     * Definitions of other registered kinds; never blueprints.
     *
     * @return iterable<object>
     */
    public function derive(BlueprintInterface $blueprint): iterable;
}
