<?php

declare(strict_types=1);

namespace Gcego\Chroma\Presets;

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\StyleConflictException;
use Gcego\Chroma\Slot;

/**
 * Named style recipes ("surface.card") that component slots and packages build on.
 */
final class PresetRegistry
{
    /**
     * @var array<string, ClassList>
     */
    private array $presets = [];

    private int $version = 0;

    /**
     * @param  string|list<string>|ClassList  $classes
     *
     * @throws StyleConflictException|InvalidSlotException
     */
    public function register(string $id, string|array|ClassList $classes, bool $override = false): void
    {
        if (preg_match(Slot::NamePattern, $id) !== 1) {
            throw InvalidSlotException::invalidName($id);
        }

        if (isset($this->presets[$id]) && ! $override) {
            throw StyleConflictException::duplicate('preset', $id);
        }

        $this->presets[$id] = ClassList::from($classes);
        $this->version++;
    }

    public function has(string $id): bool
    {
        return isset($this->presets[$id]);
    }

    /**
     * @throws InvalidSlotException
     */
    public function get(string $id): ClassList
    {
        return $this->presets[$id] ?? throw new InvalidSlotException(sprintf('Style preset "%s" is not registered.', $id));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->presets);
    }

    /**
     * @return array<string, ClassList>
     */
    public function all(): array
    {
        return $this->presets;
    }

    /**
     * Changes whenever a preset is registered; resolvers use it to invalidate caches.
     */
    public function version(): int
    {
        return $this->version;
    }

    public static function withDefaults(): self
    {
        $registry = new self;

        foreach (DefaultPresets::Presets as $id => $classes) {
            $registry->register($id, $classes);
        }

        return $registry;
    }
}
