<?php

declare(strict_types=1);

namespace Gcego\Forms\Reactive;

use Gcego\Forms\Schema\FieldEntry;

/**
 * A field at a concrete position in the current state: a top-level field, or a
 * repeater child inside specific item keys ("objectives.i1a2b3c4d5e.title").
 */
final readonly class FieldInstance
{
    /**
     * @param  list<string>  $keys  Repeater item keys, outermost first.
     */
    public function __construct(
        public FieldEntry $entry,
        public string $path,
        public array $keys,
    ) {}

    /**
     * The concrete path of the item this field belongs to ("objectives.i1a2b3c4d5e"), if any.
     */
    public function itemPath(): ?string
    {
        if ($this->keys === []) {
            return null;
        }

        return substr($this->path, 0, (int) strrpos($this->path, '.'));
    }
}
