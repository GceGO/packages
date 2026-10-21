<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Metadata;

/**
 * A blueprint's presentation and identity values.
 *
 * Name and slug are identity; title, description and icon are presentation. The
 * title is derived from the name when no Title definition is declared, and
 * the slug is always derived from the name.
 */
final readonly class BlueprintMetadata
{
    public function __construct(
        public string $name,
        public string $title,
        public string $slug,
        public ?string $description,
        public bool $titleDerived,
        public ?string $icon = null,
    ) {}

    /**
     * @return array{name: string, title: string, slug: string, description: string|null, titleDerived: bool, icon: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'titleDerived' => $this->titleDerived,
            'icon' => $this->icon,
        ];
    }
}
