<?php

declare(strict_types=1);

namespace Gcego\Modules\Context;

use Gcego\Modules\Definitions\ModuleDefinition;

/**
 * The module the current page belongs to (trusted definition metadata),
 * built per request by ModuleContextResolver. It never holds a user: who is
 * asking is gcego/security's request context.
 */
final readonly class ModuleContext
{
    public function __construct(
        public ModuleDefinition $module,
        public string $page,
        public ?string $tenant = null,
    ) {}

    public function name(): string
    {
        return $this->module->name;
    }

    public function title(): string
    {
        return $this->module->title;
    }

    /**
     * @return list<string>
     */
    public function resources(): array
    {
        return array_map(static fn ($resource): string => $resource->name, $this->module->resources);
    }
}
