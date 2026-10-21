<?php

declare(strict_types=1);

namespace Gcego\Chroma;

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Exceptions\InvalidClassException;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Components\Contracts\ConfigurationNode;

/**
 * Instance-level Tailwind CSS styling for one component.
 *
 *     new Tailwind('rounded-2xl', 'border', 'bg-white')          // shorthand: the Wrapper (root) slot
 *     new Tailwind(
 *         new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border']),
 *         new Slot(Tailwind::Header, ['px-6', 'py-4']),
 *     )
 *
 * A configuration node (gcego/morfaw ConfigurationNode): pass it to a
 * component's constructor; it is never a child and never rendered.
 *
 * Rules: strings and lists of strings are shorthand for one Wrapper slot,
 * applied before any explicit Slot. Slots apply in declaration order, so
 * the same slot may appear more than once (for example Remove, then Merge).
 * Which slots exist is declared by each component (see SlotRegistry).
 */
final readonly class Tailwind implements ConfigurationNode
{
    /**
     * The component's outermost element.
     */
    public const string Wrapper = 'root';

    public const string Header = 'header';

    public const string Title = 'title';

    public const string Description = 'description';

    public const string Body = 'body';

    public const string Content = 'content';

    public const string Footer = 'footer';

    public const string Label = 'label';

    public const string Icon = 'icon';

    public const string Actions = 'actions';

    /**
     * @var list<Slot>
     */
    public array $slots;

    /**
     * @param  string|list<string>|Slot  ...$items
     *
     * @throws InvalidSlotException|InvalidClassException
     */
    public function __construct(string|array|Slot ...$items)
    {
        $shorthand = [];
        $slots = [];

        foreach ($items as $key => $item) {
            if (is_string($key)) {
                throw InvalidSlotException::invalidItem('a named argument "'.$key.'"');
            }

            if ($item instanceof Slot) {
                $slots[] = $item;

                continue;
            }

            foreach (is_string($item) ? [$item] : $item as $class) {
                if (! is_string($class)) {
                    throw InvalidClassException::type(get_debug_type($class));
                }

                $shorthand[] = $class;
            }
        }

        $this->slots = $shorthand === [] ? $slots : [new Slot(self::Wrapper, $shorthand), ...$slots];
    }

    /**
     * @return list<Slot>
     */
    public function slotsFor(string $slot): array
    {
        return array_values(array_filter($this->slots, static fn (Slot $candidate): bool => $candidate->name === $slot));
    }

    public function has(string $slot): bool
    {
        return $this->slotsFor($slot) !== [];
    }

    /**
     * @return list<string>
     */
    public function slotNames(): array
    {
        return array_values(array_unique(array_map(static fn (Slot $slot): string => $slot->name, $this->slots)));
    }

    public function apply(string $slot, ClassList $base, ClassMerger $merger = new ClassMerger): ClassList
    {
        foreach ($this->slotsFor($slot) as $candidate) {
            $base = $candidate->applyTo($base, $merger);
        }

        return $base;
    }

    /**
     * @return array{slots: list<array{name: string, mode: string, classes: list<string>}>}
     */
    public function normalize(): array
    {
        return ['slots' => array_map(static fn (Slot $slot): array => $slot->toArray(), $this->slots)];
    }
}
