<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Contracts\ReferencesClasses;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\ClassReferences;

/**
 * A definition naming exactly one class: Table::make(SubjectsTable::class).
 */
abstract readonly class ReferenceDefinition implements ReferencesClasses
{
    /**
     * @param  class-string  $class
     */
    final protected function __construct(private string $class) {}

    /**
     * @param  class-string  $class
     *
     * @throws InvalidDefinitionException
     */
    public static function make(string $class): static
    {
        return new static(ClassReferences::one(static::definitionKey(), $class));
    }

    final public function key(): string
    {
        return static::definitionKey();
    }

    /**
     * @return class-string
     */
    public function value(): string
    {
        return $this->class;
    }

    public function classes(): array
    {
        return [$this->class];
    }
}
