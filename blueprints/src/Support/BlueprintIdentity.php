<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Support;

/**
 * The stable identity of a blueprint definition.
 *
 *  - name:  the logical identifier ("subjects"); the key registries use;
 *  - slug:  the URL-safe form, derived from the name;
 *  - class: the concrete PHP class;
 *  - type:  "blueprint".
 *
 * The title is presentation, not identity. Runtime instance identity (which
 * PHP object) is deliberately not part of it: equal declarations have equal identities.
 */
final readonly class BlueprintIdentity
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $class,
        public string $type,
    ) {}

    public function key(): string
    {
        return $this->name;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->slug === $other->slug && $this->class === $other->class && $this->type === $other->type;
    }

    /**
     * @return array{name: string, slug: string, class: class-string, type: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'class' => $this->class, 'type' => $this->type];
    }
}
