<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

/**
 * A definition whose value names classes (a model, a table, pages).
 *
 * References are stored as class names and are never autoloaded or
 * instantiated by gcego/blueprints itself; the BlueprintValidator checks
 * them, through ReferenceRule adapters, when the registry resolves the blueprint.
 */
interface ReferencesClasses extends BlueprintDefinition
{
    /**
     * @return list<class-string> In declaration order.
     */
    public function classes(): array;
}
