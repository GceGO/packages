<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

/**
 * Something that can describe itself as plain, transport-independent data.
 *
 * Implementations must return only null, scalars, arrays, enums or other
 * normalizable values, never closures, resources or service objects.
 */
interface Normalizable
{
    public function normalize(): mixed;
}
