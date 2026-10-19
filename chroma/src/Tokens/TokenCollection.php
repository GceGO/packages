<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tokens;

use ArrayIterator;
use Countable;
use Gcego\Chroma\Exceptions\InvalidTokenException;
use IteratorAggregate;
use Traversable;

/**
 * An immutable set of token values, keyed by token name.
 *
 * @implements IteratorAggregate<string, DesignToken>
 */
final readonly class TokenCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<string, DesignToken>
     */
    private array $tokens;

    /**
     * @param  array<mixed>  $values  name => value
     *
     * @throws InvalidTokenException
     */
    public function __construct(array $values = [])
    {
        $tokens = [];

        foreach ($values as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                throw InvalidTokenException::invalidValue(is_string($name) ? $name : (string) $name, 'string');
            }

            $tokens[$name] = new DesignToken($name, trim($value));
        }

        ksort($tokens);
        $this->tokens = $tokens;
    }

    public static function light(): self
    {
        return new self(array_map(static fn (array $definition): string => $definition[1], DefaultTokens::Definitions));
    }

    public static function dark(): self
    {
        return new self(array_map(static fn (array $definition): string => $definition[2], DefaultTokens::Definitions));
    }

    public function get(string $name): ?string
    {
        return isset($this->tokens[$name]) ? $this->tokens[$name]->value : null;
    }

    public function has(string $name): bool
    {
        return isset($this->tokens[$name]);
    }

    /**
     * A copy with $overrides replacing these values.
     */
    public function with(self $overrides): self
    {
        return new self(array_merge($this->toArray(), $overrides->toArray()));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_map(static fn (DesignToken $token): string => $token->value, $this->tokens);
    }

    /**
     * `--gcego-primary: #2563eb; …` in name order.
     */
    public function declarations(): string
    {
        return implode(' ', array_map(static fn (DesignToken $token): string => $token->declaration(), $this->tokens));
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    /**
     * @return Traversable<string, DesignToken>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tokens);
    }
}
