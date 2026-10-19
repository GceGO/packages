<?php

declare(strict_types=1);

namespace Gcego\Chroma\Slots;

use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\StyleConflictException;
use Gcego\Chroma\Slot;
use Gcego\Components\Contracts\ComponentInterface;

/**
 * Which slots each component class supports (with defaults), and
 * application-level slot overrides.
 *
 * Lookup for a component of class C: definitions registered for exactly C,
 * else C's nearest parent class with inherit: true, else the first
 * registered inherit: true interface C implements (same rules as the
 * runtime's RendererRegistry). Overrides are collected from all matching
 * targets, least specific first: interfaces, then parents, then C itself.
 */
final class SlotRegistry
{
    /**
     * @var array<string, array{slots: array<string, SlotDefinition>, inherit: bool}>
     */
    private array $definitions = [];

    /**
     * @var array<string, list<Slot>>
     */
    private array $overrides = [];

    /**
     * @var array<string, array<string, SlotDefinition>|false>
     */
    private array $resolved = [];

    private int $version = 0;

    /**
     * @param  class-string  $target  A ComponentInterface class or interface.
     * @param  list<SlotDefinition>  $slots
     *
     * @throws StyleConflictException|InvalidSlotException
     */
    public function register(string $target, array $slots, bool $inherit = true, bool $override = false): void
    {
        $this->assertTarget($target);

        if (isset($this->definitions[$target]) && ! $override) {
            throw StyleConflictException::duplicate('slot set', $target);
        }

        $indexed = [];

        foreach ($slots as $slot) {
            if (! $slot instanceof SlotDefinition) {
                throw InvalidSlotException::invalidItem(get_debug_type($slot));
            }

            $indexed[$slot->name] = $slot;
        }

        $this->definitions[$target] = ['slots' => $indexed, 'inherit' => $inherit];
        $this->resolved = [];
        $this->version++;
    }

    /**
     * Application-level overrides for a component class or interface.
     *
     * @param  class-string  $target
     */
    public function override(string $target, Slot ...$slots): void
    {
        $this->assertTarget($target);
        $this->overrides[$target] = [...($this->overrides[$target] ?? []), ...array_values($slots)];
        $this->version++;
    }

    /**
     * The supported slots of a component, or null when no package declared any.
     *
     * @return array<string, SlotDefinition>|null
     */
    public function for(ComponentInterface|string $component): ?array
    {
        $class = is_string($component) ? $component : $component::class;

        if (! isset($this->resolved[$class])) {
            $this->resolved[$class] = $this->resolve($class) ?? false;
        }

        return $this->resolved[$class] ?: null;
    }

    /**
     * Override slots for one slot of a component class, least specific target first.
     *
     * @return list<Slot>
     */
    public function overridesFor(string $class, string $slot): array
    {
        $targets = [];

        foreach (array_keys($this->overrides) as $target) {
            if (interface_exists($target) && is_a($class, $target, true)) {
                $targets[] = $target;
            }
        }

        $parents = [];

        for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
            if (isset($this->overrides[$parent])) {
                array_unshift($parents, $parent);
            }
        }

        $targets = [...$targets, ...$parents];

        if (isset($this->overrides[$class])) {
            $targets[] = $class;
        }

        $slots = [];

        foreach ($targets as $target) {
            foreach ($this->overrides[$target] as $candidate) {
                if ($candidate->name === $slot) {
                    $slots[] = $candidate;
                }
            }
        }

        return $slots;
    }

    /**
     * @return array<string, list<string>> target => slot names
     */
    public function registrations(): array
    {
        return array_map(static fn (array $definition): array => array_keys($definition['slots']), $this->definitions);
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return array<string, SlotDefinition>|null
     */
    private function resolve(string $class): ?array
    {
        if (isset($this->definitions[$class])) {
            return $this->definitions[$class]['slots'];
        }

        if (! class_exists($class)) {
            return null;
        }

        for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
            if (isset($this->definitions[$parent]) && $this->definitions[$parent]['inherit']) {
                return $this->definitions[$parent]['slots'];
            }
        }

        foreach ($this->definitions as $target => $definition) {
            if ($definition['inherit'] && interface_exists($target) && is_a($class, $target, true)) {
                return $definition['slots'];
            }
        }

        return null;
    }

    private function assertTarget(string $target): void
    {
        if (! (class_exists($target) || interface_exists($target)) || ! is_a($target, ComponentInterface::class, true)) {
            throw new InvalidSlotException(sprintf('Slot target %s must be an existing class or interface implementing %s.', $target, ComponentInterface::class));
        }
    }
}
