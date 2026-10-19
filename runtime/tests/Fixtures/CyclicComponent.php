<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentCollection;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\ConfigurationCollection;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyCollection;
use Gcego\Components\Validation\ValidationResult;

/**
 * A hostile ComponentInterface implementation that contains itself.
 */
final class CyclicComponent implements ComponentInterface
{
    public static function definition(): ComponentDefinition
    {
        return Component::definition();
    }

    public function type(): string
    {
        return 'fixtures.cyclic';
    }

    public function id(): ?string
    {
        return null;
    }

    public function configurations(): ConfigurationCollection
    {
        return new ConfigurationCollection;
    }

    public function properties(): PropertyCollection
    {
        return new PropertyCollection;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $fallback;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function effectiveProperties(): array
    {
        return [];
    }

    public function children(): ComponentCollection
    {
        return new ComponentCollection($this);
    }

    public function childCount(): int
    {
        return 1;
    }

    public function hasChildren(): bool
    {
        return true;
    }

    public function validate(): ValidationResult
    {
        return new ValidationResult;
    }

    public function assertValid(): static
    {
        return $this;
    }

    public function normalize(): array
    {
        return [];
    }
}
