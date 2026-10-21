<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Contracts\BlueprintDefinition;

/**
 * A definition key Blueprint::define() accepts, the class that owns it, and
 * whether every blueprint must declare it.
 */
final readonly class DefinitionType
{
    /**
     * @param  class-string<BlueprintDefinition>  $class
     */
    public function __construct(
        public string $key,
        public string $class,
        public bool $required = false,
    ) {}

    /**
     * @return array{key: string, class: class-string<BlueprintDefinition>, required: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'class' => $this->class, 'required' => $this->required];
    }
}
