<?php

declare(strict_types=1);

namespace Gcego\Swatches\Tokens;

use ArrayIterator;
use Countable;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use IteratorAggregate;
use Traversable;

/**
 * An immutable map of token name => TokenValue, in catalog order.
 *
 * A set may be partial (a theme's overrides) or complete (a resolved theme).
 * Only catalog tokens are accepted, so a set can never emit an unknown property.
 *
 * @implements IteratorAggregate<string, TokenValue>
 */
final readonly class TokenSet implements Countable, IteratorAggregate
{
    /**
     * @var array<string, TokenValue>
     */
    private array $values;

    /**
     * @param  array<mixed>  $values  name => "#hex" | "oklch(…)" | "{token}" | TokenValue | Color
     *
     * @throws InvalidTokenException
     */
    public function __construct(array $values = [])
    {
        $parsed = [];

        foreach ($values as $name => $value) {
            if (! is_string($name) || ! SemanticTokens::has($name)) {
                throw InvalidTokenException::unknown((string) $name);
            }

            $parsed[$name] = match (true) {
                $value instanceof TokenValue => $value,
                $value instanceof Color => TokenValue::color($value),
                is_string($value) => TokenValue::parse($name, $value),
                default => throw InvalidTokenException::invalidValue($name),
            };
        }

        $ordered = [];

        foreach (SemanticTokens::names() as $name) {
            if (isset($parsed[$name])) {
                $ordered[$name] = $parsed[$name];
            }
        }

        $this->values = $ordered;
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function get(string $name): ?TokenValue
    {
        return $this->values[$name] ?? null;
    }

    /**
     * A copy where $overrides replace these values.
     */
    public function with(self $overrides): self
    {
        return new self(array_merge($this->values, $overrides->values));
    }

    /**
     * Only the given token names.
     *
     * @param  list<string>  $names
     */
    public function only(array $names): self
    {
        return new self(array_intersect_key($this->values, array_flip($names)));
    }

    /**
     * Values that differ from $base (or are missing from it).
     */
    public function diff(self $base): self
    {
        return new self(array_filter(
            $this->values,
            static fn (TokenValue $value, string $name): bool => ! $base->has($name) || ! $value->equals($base->values[$name]),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * The concrete color of a token, following {references}.
     *
     * @throws InvalidTokenException for unknown tokens, broken references and cycles
     */
    public function resolve(string $name): Color
    {
        $chain = [];
        $current = $name;

        while (true) {
            if (in_array($current, $chain, true)) {
                throw InvalidTokenException::circular([...$chain, $current]);
            }

            $chain[] = $current;
            $value = $this->values[$current] ?? null;

            if ($value === null) {
                throw count($chain) === 1
                    ? InvalidTokenException::unknown($name)
                    : InvalidTokenException::brokenReference($chain[count($chain) - 2], $current);
            }

            if ($value->color !== null) {
                return $value->color;
            }

            $current = (string) $value->reference;
        }
    }

    /**
     * Tokens that reference $name directly or through other references.
     *
     * @return list<string>
     */
    public function dependents(string $name): array
    {
        $dependents = [];
        $queue = [$name];

        while ($queue !== []) {
            $target = array_shift($queue);

            foreach ($this->values as $token => $value) {
                if ($value->reference === $target && ! in_array($token, $dependents, true) && $token !== $name) {
                    $dependents[] = $token;
                    $queue[] = $token;
                }
            }
        }

        return $dependents;
    }

    /**
     * @return array<string, string> name => authoring notation
     */
    public function toArray(): array
    {
        return array_map(static fn (TokenValue $value): string => $value->toString(), $this->values);
    }

    /**
     * `--gcego-primary: #0b7570; …` in catalog order (values are serialized, never echoed).
     *
     * @return list<string>
     */
    public function declarations(): array
    {
        $declarations = [];

        foreach ($this->values as $name => $value) {
            $declarations[] = Token::property($name).': '.$value->toCss().';';
        }

        return $declarations;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function count(): int
    {
        return count($this->values);
    }

    /**
     * @return Traversable<string, TokenValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}
