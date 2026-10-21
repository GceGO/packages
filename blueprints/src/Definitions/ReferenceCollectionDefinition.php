<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Countable;
use Gcego\Blueprints\Contracts\ReferencesClasses;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\ClassReferences;

/**
 * A definition naming an ordered list of distinct classes:
 * Pages::make([ListSubjects::class, CreateSubject::class]).
 */
abstract readonly class ReferenceCollectionDefinition implements Countable, ReferencesClasses
{
    /**
     * @param  list<class-string>  $classes
     */
    final protected function __construct(private array $classes) {}

    /**
     * @param  list<class-string>  $classes
     *
     * @throws InvalidDefinitionException for an empty or keyed array, a non-string element, an invalid class name or a duplicate.
     */
    public static function make(array $classes): static
    {
        return new static(ClassReferences::list(static::definitionKey(), $classes));
    }

    final public function key(): string
    {
        return static::definitionKey();
    }

    /**
     * @return list<class-string>
     */
    public function value(): array
    {
        return $this->classes;
    }

    public function classes(): array
    {
        return $this->classes;
    }

    public function contains(string $class): bool
    {
        $folded = strtolower(ltrim($class, '\\'));

        foreach ($this->classes as $reference) {
            if (strtolower($reference) === $folded) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->classes);
    }
}
