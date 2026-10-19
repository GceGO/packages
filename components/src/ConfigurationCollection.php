<?php

declare(strict_types=1);

namespace Gcego\Components;

use ArrayIterator;
use Countable;
use Gcego\Components\Contracts\ConfigurationNode;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered list of non-renderable configuration nodes.
 *
 * @implements IteratorAggregate<int, ConfigurationNode>
 */
final readonly class ConfigurationCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<ConfigurationNode>
     */
    private array $configurations;

    public function __construct(ConfigurationNode ...$configurations)
    {
        $this->configurations = array_values($configurations);
    }

    /**
     * @param  class-string<ConfigurationNode>  $class
     */
    public function has(string $class): bool
    {
        return $this->first($class) !== null;
    }

    /**
     * @template T of ConfigurationNode
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function first(string $class): ?ConfigurationNode
    {
        foreach ($this->configurations as $configuration) {
            if ($configuration instanceof $class) {
                return $configuration;
            }
        }

        return null;
    }

    /**
     * @template T of ConfigurationNode
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function ofClass(string $class): array
    {
        $matches = [];

        foreach ($this->configurations as $configuration) {
            if ($configuration instanceof $class) {
                $matches[] = $configuration;
            }
        }

        return $matches;
    }

    /**
     * @return list<ConfigurationNode>
     */
    public function all(): array
    {
        return $this->configurations;
    }

    public function isEmpty(): bool
    {
        return $this->configurations === [];
    }

    public function count(): int
    {
        return count($this->configurations);
    }

    /**
     * @return Traversable<int, ConfigurationNode>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->configurations);
    }
}
