<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Metadata;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Support\Naming;
use Gcego\Components\Contracts\ComponentInterface;

/**
 * A typed, read-only view over a blueprint's canonical properties.
 *
 * Properties remain the single source of truth; this object only applies
 * the documented conventions:
 *  - title: the Title property, else the humanized name ("academic-subjects" -> "Academic Subjects");
 *  - slug:  the Slug property, else the slugified name ("academic_subjects" -> "academic-subjects").
 *
 * Name and slug are identity; title, description and icon are presentation.
 */
final readonly class BlueprintMetadata
{
    public function __construct(
        public ?string $name,
        public ?string $title,
        public ?string $slug,
        public ?string $description,
        public ?string $icon,
        public bool $titleDerived,
        public bool $slugDerived,
    ) {}

    public static function of(ComponentInterface $blueprint): self
    {
        $name = self::string($blueprint->get(Blueprint::Name));
        $validName = Naming::isName($name) ? $name : null;
        $title = self::string($blueprint->get(Blueprint::Title));
        $slug = self::string($blueprint->get(Blueprint::Slug));

        return new self(
            $name,
            $title ?? ($validName === null ? null : Naming::humanize($validName)),
            $slug ?? ($validName === null ? null : Naming::slugify($validName)),
            self::string($blueprint->get(Blueprint::Description)),
            self::string($blueprint->get(Blueprint::Icon)),
            $title === null && $validName !== null,
            $slug === null && $validName !== null,
        );
    }

    /**
     * @return array{name: string|null, title: string|null, slug: string|null, description: string|null, icon: string|null, titleDerived: bool, slugDerived: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'titleDerived' => $this->titleDerived,
            'slugDerived' => $this->slugDerived,
        ];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
