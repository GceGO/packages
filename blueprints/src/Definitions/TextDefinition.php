<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Contracts\BlueprintDefinition;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

/**
 * A definition holding one validated, trimmed string (Name, Title, Description).
 */
abstract readonly class TextDefinition implements BlueprintDefinition
{
    final protected function __construct(private string $text) {}

    /**
     * @throws InvalidDefinitionException
     */
    public static function make(string $value): static
    {
        return new static(static::normalize(trim($value)));
    }

    /**
     * Validate the trimmed value and return its normalized form.
     *
     * @throws InvalidDefinitionException
     */
    abstract protected static function normalize(string $value): string;

    final public function key(): string
    {
        return static::definitionKey();
    }

    public function value(): string
    {
        return $this->text;
    }
}
