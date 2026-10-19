<?php

declare(strict_types=1);

namespace Gcego\Components\Inspection;

use Gcego\Components\Validation\ValidationError;

/**
 * A structured, read-only snapshot of one component for developer tooling.
 */
final readonly class ComponentInspection
{
    /**
     * @param  class-string  $class
     * @param  list<string>  $supportedProperties
     * @param  list<string>  $requiredProperties
     * @param  array<string, mixed>  $explicitProperties  Normalized explicit values.
     * @param  array<string, mixed>  $effectiveProperties  Normalized explicit values merged over defaults.
     * @param  array<string, mixed>  $defaults  Normalized schema defaults.
     * @param  list<string>|null  $allowedChildren  Null when any child is accepted.
     * @param  list<string>  $configurationClasses
     * @param  list<ValidationError>  $errors
     */
    public function __construct(
        public string $class,
        public string $type,
        public ?string $id,
        public string $path,
        public int $depth,
        public array $supportedProperties,
        public array $requiredProperties,
        public bool $allowsUnknownProperties,
        public array $explicitProperties,
        public array $effectiveProperties,
        public array $defaults,
        public int $childCount,
        public bool $childrenAllowed,
        public ?array $allowedChildren,
        public int $minChildren,
        public ?int $maxChildren,
        public array $configurationClasses,
        public array $errors,
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
            'class' => $this->class,
            'type' => $this->type,
            'id' => $this->id,
            'path' => $this->path,
            'depth' => $this->depth,
            'supportedProperties' => $this->supportedProperties,
            'requiredProperties' => $this->requiredProperties,
            'allowsUnknownProperties' => $this->allowsUnknownProperties,
            'explicitProperties' => $this->explicitProperties,
            'effectiveProperties' => $this->effectiveProperties,
            'defaults' => $this->defaults,
            'childCount' => $this->childCount,
            'childrenAllowed' => $this->childrenAllowed,
            'allowedChildren' => $this->allowedChildren,
            'minChildren' => $this->minChildren,
            'maxChildren' => $this->maxChildren,
            'configurationClasses' => $this->configurationClasses,
            'valid' => $this->isValid(),
            'errors' => array_map(static fn (ValidationError $error): array => $error->toArray(), $this->errors),
        ];
    }
}
