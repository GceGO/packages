<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Composition;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\ComponentPath;

/**
 * A part together with the role it declared and where it sits in the blueprint tree.
 */
final readonly class ClassifiedPart
{
    public function __construct(
        public BlueprintPart $part,
        public BlueprintRole $role,
        public ComponentPath $path,
    ) {}

    /**
     * @return array{role: string, multiple: bool, path: string, class: class-string, type: string}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->name,
            'multiple' => $this->role->multiple,
            'path' => $this->path->toString(),
            'class' => $this->part::class,
            'type' => $this->part->type(),
        ];
    }
}
