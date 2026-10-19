<?php

declare(strict_types=1);

namespace Gcego\Chroma\Slots;

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Slot;

/**
 * A slot a component supports, with its default appearance: an optional
 * preset plus the component package's own classes on top.
 */
final readonly class SlotDefinition
{
    public ClassList $classes;

    /**
     * @param  string|list<string>|ClassList  $classes
     */
    public function __construct(
        public string $name,
        public ?string $preset = null,
        string|array|ClassList $classes = [],
        public ?string $description = null,
    ) {
        if (preg_match(Slot::NamePattern, $name) !== 1) {
            throw InvalidSlotException::invalidName($name);
        }

        if ($preset !== null && preg_match(Slot::NamePattern, $preset) !== 1) {
            throw InvalidSlotException::invalidName($preset);
        }

        $this->classes = ClassList::from($classes);
    }
}
