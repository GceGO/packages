<?php

declare(strict_types=1);

namespace Gcego\Blueprints;

use ArrayIterator;
use Closure;
use Countable;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Blueprints\Exceptions\DuplicateBlueprintException;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use Gcego\Blueprints\Exceptions\UnknownBlueprintException;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, in-memory set of explicitly supplied blueprints, keyed by name.
 *
 * It never scans directories or keeps global state; discovery and
 * application-wide registration belong to gcego/registry.
 *
 * @implements IteratorAggregate<string, BlueprintInterface>
 */
final readonly class BlueprintCollection implements BlueprintResolver, Countable, IteratorAggregate
{
    /**
     * @var array<string, BlueprintInterface>
     */
    private array $blueprints;

    /**
     * @throws InvalidBlueprintException for a blueprint without a valid name.
     * @throws DuplicateBlueprintException for two different blueprints with the same name.
     */
    public function __construct(BlueprintInterface ...$blueprints)
    {
        $indexed = [];

        foreach ($blueprints as $blueprint) {
            $name = $blueprint->identity()->name;

            if (isset($indexed[$name])) {
                if ($indexed[$name] === $blueprint) {
                    continue;
                }

                throw DuplicateBlueprintException::forName($name, $indexed[$name]::class, $blueprint::class);
            }

            $indexed[$name] = $blueprint;
        }

        $this->blueprints = $indexed;
    }

    public function has(string $name): bool
    {
        return isset($this->blueprints[$name]);
    }

    public function get(string $name): ?BlueprintInterface
    {
        return $this->blueprints[$name] ?? null;
    }

    /**
     * @throws UnknownBlueprintException
     */
    public function resolve(string $name): BlueprintInterface
    {
        return $this->blueprints[$name] ?? throw UnknownBlueprintException::forName($name, $this->names());
    }

    public function names(): array
    {
        return array_keys($this->blueprints);
    }

    /**
     * @return array<string, BlueprintInterface>
     */
    public function all(): array
    {
        return $this->blueprints;
    }

    /**
     * Copy with blueprints added; existing names are not replaced.
     *
     * @throws DuplicateBlueprintException
     */
    public function with(BlueprintInterface ...$blueprints): self
    {
        return new self(...array_values($this->blueprints), ...$blueprints);
    }

    public function without(string ...$names): self
    {
        return new self(...array_values(array_diff_key($this->blueprints, array_flip($names))));
    }

    /**
     * @param  Closure(BlueprintInterface): bool  $predicate
     */
    public function filter(Closure $predicate): self
    {
        return new self(...array_values(array_filter($this->blueprints, $predicate)));
    }

    /**
     * @return list<BlueprintInterface>
     */
    public function toArray(): array
    {
        return array_values($this->blueprints);
    }

    public function isEmpty(): bool
    {
        return $this->blueprints === [];
    }

    public function count(): int
    {
        return count($this->blueprints);
    }

    /**
     * @return Traversable<string, BlueprintInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->blueprints);
    }
}
