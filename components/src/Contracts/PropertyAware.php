<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

use Gcego\Components\Collections\PropertyCollection;

interface PropertyAware
{
    /**
     * The explicitly declared properties, in declaration order.
     */
    public function properties(): PropertyCollection;

    /**
     * The explicit value, otherwise the schema default, otherwise $fallback.
     */
    public function get(string $key, mixed $fallback = null): mixed;

    /**
     * Whether the property has an explicit value or a schema default.
     */
    public function has(string $key): bool;

    /**
     * Schema defaults merged with explicit values, sorted by key.
     *
     * @return array<string, mixed>
     */
    public function effectiveProperties(): array;
}
