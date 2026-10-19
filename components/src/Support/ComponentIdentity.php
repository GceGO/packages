<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

use Gcego\Components\ComponentPath;

/**
 * The three distinct identities of a component occurrence:
 *
 *  - type:       what kind of component it is (shared by all instances);
 *  - explicitId: the optional developer-provided id, stable across requests;
 *  - path:       where this occurrence sits in a particular tree.
 */
final readonly class ComponentIdentity
{
    public function __construct(
        public string $type,
        public ?string $explicitId,
        public ComponentPath $path,
    ) {}

    /**
     * Unique within one tree; deterministic for an unchanged tree.
     */
    public function occurrenceKey(): string
    {
        return $this->path->toString();
    }

    /**
     * The explicit id when declared, otherwise the occurrence key.
     */
    public function stableKey(): string
    {
        return $this->explicitId ?? $this->occurrenceKey();
    }

    public function hasExplicitId(): bool
    {
        return $this->explicitId !== null;
    }
}
