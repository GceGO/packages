<?php

declare(strict_types=1);

namespace Gcego\Forms\Schema;

use Gcego\Forms\Form;

/**
 * The compiled, immutable structure of a Form: fields in declaration order
 * with their state paths, conditions, steps and dependencies.
 */
final readonly class FormSchema
{
    /**
     * @param  list<FieldEntry>  $entries
     * @param  list<string>  $live  Field paths other fields depend on (rendered with live bindings).
     * @param  array<string, list<string>>  $dependencies  Field path => paths it depends on.
     * @param  list<string>  $steps  Wizard step labels (empty without a Wizard).
     */
    public function __construct(
        public Form $form,
        public array $entries,
        public array $live,
        public array $dependencies,
        public array $steps,
    ) {}

    /**
     * Entries directly inside the given repeater template (null: top level).
     *
     * @return list<FieldEntry>
     */
    public function children(?string $repeater = null): array
    {
        return array_values(array_filter($this->entries, static fn (FieldEntry $entry): bool => $entry->repeater === $repeater));
    }

    public function entry(string $path): ?FieldEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->path === $path) {
                return $entry;
            }
        }

        return null;
    }

    public function byComponentPath(string $componentPath): ?FieldEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->componentPath === $componentPath) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_map(static fn (FieldEntry $entry): string => $entry->path, $this->entries);
    }

    public function isLive(string $path): bool
    {
        return in_array($path, $this->live, true);
    }

    public function stepCount(): int
    {
        return count($this->steps);
    }
}
