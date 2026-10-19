<?php

declare(strict_types=1);

namespace Gcego\Components\Validation;

use ArrayIterator;
use Countable;
use Gcego\Components\Exceptions\InvalidComponentException;
use IteratorAggregate;
use Traversable;

/**
 * The immutable outcome of validating a component tree.
 *
 * @implements IteratorAggregate<int, ValidationError>
 */
final readonly class ValidationResult implements Countable, IteratorAggregate
{
    /**
     * @var list<ValidationError>
     */
    private array $errors;

    public function __construct(ValidationError ...$errors)
    {
        $this->errors = array_values($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * @return list<ValidationError>
     */
    public function errorsAt(string $path): array
    {
        return array_values(array_filter($this->errors, static fn (ValidationError $error): bool => $error->path === $path));
    }

    /**
     * @return list<ValidationError>
     */
    public function errorsWithCode(ErrorCode $code): array
    {
        return array_values(array_filter($this->errors, static fn (ValidationError $error): bool => $error->code === $code));
    }

    public function merge(self $other): self
    {
        return new self(...$this->errors, ...$other->errors);
    }

    /**
     * Strict mode.
     *
     * @throws InvalidComponentException
     */
    public function throwIfInvalid(): void
    {
        if ($this->errors !== []) {
            throw InvalidComponentException::fromResult($this);
        }
    }

    public function format(): string
    {
        return implode("\n\n", array_map(static fn (ValidationError $error): string => $error->format(), $this->errors));
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function toArray(): array
    {
        return array_map(static fn (ValidationError $error): array => $error->toArray(), $this->errors);
    }

    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * @return Traversable<int, ValidationError>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->errors);
    }
}
