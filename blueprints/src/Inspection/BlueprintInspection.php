<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Inspection;

use Gcego\Blueprints\Validation\BlueprintValidationError;

/**
 * A structured, read-only snapshot of a blueprint for tooling.
 */
final readonly class BlueprintInspection
{
    /**
     * @param  class-string  $class
     * @param  array<string, list<string>>  $parts  Role => part paths, in order of first appearance.
     * @param  list<string>  $missingRequiredRoles
     * @param  list<string>  $missingOptionalRoles  Allowed by the policy but absent (empty when every role is allowed).
     * @param  list<BlueprintValidationError>  $conflicts  Composition problems only.
     * @param  list<BlueprintValidationError>  $errors  Every validation error.
     * @param  array<string, mixed>  $normalized
     */
    public function __construct(
        public ?string $name,
        public ?string $title,
        public ?string $slug,
        public string $class,
        public string $type,
        public int $componentCount,
        public array $parts,
        public array $missingRequiredRoles,
        public array $missingOptionalRoles,
        public array $conflicts,
        public array $errors,
        public array $normalized,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'class' => $this->class,
            'type' => $this->type,
            'componentCount' => $this->componentCount,
            'parts' => $this->parts,
            'missingRequiredRoles' => $this->missingRequiredRoles,
            'missingOptionalRoles' => $this->missingOptionalRoles,
            'conflicts' => array_map(static fn (BlueprintValidationError $error): array => $error->toArray(), $this->conflicts),
            'valid' => $this->isValid(),
            'errors' => array_map(static fn (BlueprintValidationError $error): array => $error->toArray(), $this->errors),
            'normalized' => $this->normalized,
        ];
    }
}
