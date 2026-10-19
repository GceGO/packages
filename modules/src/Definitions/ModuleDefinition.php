<?php

declare(strict_types=1);

namespace Gcego\Modules\Definitions;

use Gcego\Modules\Module;
use Gcego\Modules\Operation;
use Gcego\Pages\Page;
use Gcego\Security\Contracts\Requirement;

/**
 * A compiled module: the (extended) definition, its normalized resources and
 * its custom pages, with names and paths decided. Immutable.
 */
final readonly class ModuleDefinition
{
    /**
     * @param  list<Requirement>  $requirements
     * @param  list<ResourceDefinition>  $resources
     * @param  list<Page>  $pages  custom module pages, already named and placed
     */
    public function __construct(
        public Module $module,
        public string $name,
        public string $title,
        public ?string $description,
        public ?string $icon,
        public ?string $slug,
        public ?string $version,
        public ?bool $navigation,
        public string $navigationGroup,
        public int $navigationOrder,
        public array $requirements,
        public array $resources,
        public array $pages,
    ) {}

    public function identity(): string
    {
        return 'module:'.$this->name;
    }

    public function resource(string $name): ?ResourceDefinition
    {
        foreach ($this->resources as $resource) {
            if ($resource->name === $name) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * What each generated page is: page name => [resource, relation, operation]
     * (custom pages have a null operation; module pages a null resource).
     *
     * @return array<string, array{resource: ?string, relation: ?string, operation: ?string}>
     */
    public function pageMap(): array
    {
        $map = [];

        foreach ($this->resources as $resource) {
            foreach ($resource->operations as $operation) {
                $map[$resource->pageName($operation)] = ['resource' => $resource->name, 'relation' => null, 'operation' => $operation->value];
            }

            foreach ($resource->relations as $relation) {
                foreach ($relation->operations as $operation) {
                    $map[$relation->pageName($operation)] = ['resource' => $resource->name, 'relation' => $relation->name, 'operation' => $operation->value];
                }
            }

            foreach ($resource->pages as $page) {
                $map[$page->pageName()] = ['resource' => $resource->name, 'relation' => null, 'operation' => null];
            }
        }

        foreach ($this->pages as $page) {
            $map[$page->pageName()] = ['resource' => null, 'relation' => null, 'operation' => null];
        }

        return $map;
    }

    /**
     * Whether a resource's operation page exists.
     */
    public function hasPage(string $resource, Operation $operation): bool
    {
        return $this->resource($resource)?->has($operation) === true;
    }
}
