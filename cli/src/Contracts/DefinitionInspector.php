<?php

declare(strict_types=1);

namespace Gcego\Cli\Contracts;

use Gcego\Registry\Index\DefinitionDescriptor;

/**
 * Adds a section to gcego:inspect for definitions of some kinds. Read-only;
 * return plain, JSON-serializable data and never secrets.
 */
interface DefinitionInspector
{
    /**
     * Stable identifier of this inspector ("acme.reports").
     */
    public function id(): string;

    /**
     * Registry kinds this inspector describes (["report-card"]).
     *
     * @return list<string>
     */
    public function kinds(): array;

    /**
     * @return array<string, mixed>
     */
    public function inspect(object $definition, DefinitionDescriptor $descriptor): array;
}
