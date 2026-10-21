<?php

declare(strict_types=1);

namespace Gcego\Components\Contracts;

use Gcego\Components\Collections\ConfigurationCollection;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * A renderable node of a declarative component tree.
 */
interface ComponentInterface extends HasChildren, Normalizable, PropertyAware, Validatable
{
    /**
     * The schema shared by every instance of the implementing class.
     */
    public static function definition(): ComponentDefinition;

    /**
     * The stable component type identifier declared by the schema.
     */
    public function type(): string;

    /**
     * The explicit developer-provided identifier, if any.
     */
    public function id(): ?string;

    public function configurations(): ConfigurationCollection;

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array;
}
