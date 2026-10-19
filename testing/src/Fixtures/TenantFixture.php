<?php

declare(strict_types=1);

namespace Gcego\Testing\Fixtures;

/**
 * A tenant created for a test: the key the TenantResolver understands
 * (what enterTenant() takes) and, optionally, the application's model.
 */
final readonly class TenantFixture
{
    public function __construct(public string $key, public mixed $model = null, public ?string $name = null) {}

    public function __toString(): string
    {
        return $this->key;
    }
}
