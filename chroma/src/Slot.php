<?php

declare(strict_types=1);

namespace Gcego\Chroma;

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Exceptions\InvalidClassException;
use Gcego\Chroma\Exceptions\InvalidSlotException;

/**
 * Classes for one named slot of a component (or one preset, in a theme).
 *
 *     new Slot(Tailwind::Wrapper, [
 *         'rounded-2xl',
 *         'bg-white',
 *     ]);
 *     new Slot(Tailwind::Wrapper, 'rounded-2xl bg-white');
 *     new Slot(Tailwind::Wrapper, ['shadow-gcego-card'], SlotMode::Remove);
 */
final readonly class Slot
{
    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_.][a-z0-9]+)*$/';

    public ClassList $classes;

    /**
     * @param  string|list<string>|ClassList  $classes  One class per array entry, or space-separated strings.
     *
     * @throws InvalidSlotException|InvalidClassException
     */
    public function __construct(
        public string $name,
        string|array|ClassList $classes = [],
        public SlotMode $mode = SlotMode::Merge,
    ) {
        if (preg_match(self::NamePattern, $name) !== 1 || strlen($name) > 64) {
            throw InvalidSlotException::invalidName($name);
        }

        $this->classes = ClassList::from($classes);
    }

    /**
     * @param  string|list<string>|ClassList  $classes
     */
    public static function replace(string $name, string|array|ClassList $classes): self
    {
        return new self($name, $classes, SlotMode::Replace);
    }

    /**
     * @param  string|list<string>|ClassList  $classes
     */
    public static function remove(string $name, string|array|ClassList $classes): self
    {
        return new self($name, $classes, SlotMode::Remove);
    }

    public function applyTo(ClassList $base, ClassMerger $merger = new ClassMerger): ClassList
    {
        return match ($this->mode) {
            SlotMode::Merge => $base->merge($this->classes, $merger),
            SlotMode::Replace => $this->classes,
            SlotMode::Remove => $base->without($this->classes),
        };
    }

    /**
     * @return array{name: string, mode: string, classes: list<string>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'mode' => $this->mode->value, 'classes' => $this->classes->all()];
    }
}
