<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Validation;

use ArrayIterator;
use Countable;
use Gcego\Blueprints\Exceptions\InvalidBlueprintException;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, BlueprintValidationError>
 */
final readonly class BlueprintValidationResult implements Countable, IteratorAggregate
{
    /**
     * @var list<BlueprintValidationError>
     */
    private array $errors;

    public function __construct(
        public string $blueprint,
        BlueprintValidationError ...$errors,
    ) {
        $this->errors = array_values($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<BlueprintValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?BlueprintValidationError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * @return list<BlueprintValidationError>
     */
    public function errorsWithCode(BlueprintErrorCode|string $code): array
    {
        $code = $code instanceof BlueprintErrorCode ? $code->value : $code;

        return array_values(array_filter($this->errors, static fn (BlueprintValidationError $error): bool => $error->code === $code));
    }

    /**
     * @return list<BlueprintValidationError>
     */
    public function errorsForKey(string $key): array
    {
        return array_values(array_filter($this->errors, static fn (BlueprintValidationError $error): bool => $error->key === $key));
    }

    /**
     * One formatted string per error, as gcego/registry kinds report them.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (BlueprintValidationError $error): string => $error->format(), $this->errors);
    }

    /**
     * @throws InvalidBlueprintException
     */
    public function throwIfInvalid(): void
    {
        if ($this->errors !== []) {
            throw InvalidBlueprintException::fromResult($this);
        }
    }

    public function format(): string
    {
        return implode("\n\n", array_map(static fn (BlueprintValidationError $error): string => $error->format(), $this->errors));
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function toArray(): array
    {
        return array_map(static fn (BlueprintValidationError $error): array => $error->toArray(), $this->errors);
    }

    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * @return Traversable<int, BlueprintValidationError>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->errors);
    }
}
