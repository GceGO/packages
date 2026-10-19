<?php

declare(strict_types=1);

namespace Gcego\Modules\Context;

use Gcego\Modules\Definitions\RelationDefinition;
use Gcego\Modules\Definitions\ResourceDefinition;
use Gcego\Modules\Operation;

/**
 * The resource (and relation, operation, record) of the current page.
 * Keys are the route's, unverified: the page and its hosts look records up
 * in scope and authorize them; this context only describes where we are.
 */
final readonly class ResourceContext
{
    public function __construct(
        public ModuleContext $module,
        public ResourceDefinition $resource,
        public ?RelationDefinition $relation,
        public ?Operation $operation,
        public ?string $recordKey,
        public ?string $parentKey,
    ) {}

    /**
     * "resource:academics.subjects" (or "relation:academics.subjects.topics").
     */
    public function identity(): string
    {
        return $this->relation?->identity() ?? $this->resource->identity();
    }

    public function isRelation(): bool
    {
        return $this->relation !== null;
    }
}
