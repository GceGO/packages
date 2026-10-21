<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

/**
 * One typed entry of a blueprint, such as Name::make('subjects') or
 * Pages::make([...]).
 *
 * A definition validates and normalizes its value when it is made, so a
 * definition object is always structurally valid. Each definition class owns
 * exactly one key, and a key is accepted by Blueprint::define() only once it
 * is in the DefinitionCatalog (the built-in definitions always are).
 */
interface BlueprintDefinition
{
    /**
     * The key every instance of this class is stored under ("name", "table", "pages").
     */
    public static function definitionKey(): string;

    /**
     * The same key, read from an instance.
     */
    public function key(): string;

    /**
     * The normalized value: a string, a class name, a list of class names,
     * or another immutable value for extension definitions.
     */
    public function value(): mixed;
}
