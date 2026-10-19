<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Repeated groups of nested entries, one per item of a to-many relation or
 * an array attribute:
 *
 *     new RepeatableEntry('objectives',
 *         new Text('title', 'Objective'),
 *         new Text('description', 'Description'),
 *     )
 *     new RepeatableEntry('topics', new Text('title'), new Text('teacher.name', 'Teacher'), new Property(RepeatableEntry::Columns, 2))
 *
 * Child paths are relative to each item. Relations they read are
 * eager-loaded with the record ("topics", "topics.teacher"), never per item.
 * Items keep a stable key (the model key or the array index). Read-only.
 */
class RepeatableEntry extends Entry
{
    public const string Columns = 'columns';

    public function view(): string
    {
        return 'gcego-infolists::entries.repeatable';
    }

    /**
     * @return list<Entry>
     */
    public function entries(): array
    {
        return array_values(array_filter($this->children()->all(), static fn (mixed $child): bool => $child instanceof Entry));
    }

    public function columns(): int
    {
        $columns = $this->get(self::Columns);

        return is_int($columns) ? $columns : 1;
    }

    /**
     * The item's own relation and its children's, prefixed ("topics", "topics.teacher").
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $relations = [$this->entryName()];

        foreach ($this->entries() as $entry) {
            foreach ($entry->relations() as $relation) {
                $relations[] = $this->entryName().'.'.$relation;
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * Items with stable keys.
     *
     * @return list<array{key: string, item: mixed}>
     */
    public function items(mixed $value, InfolistsConfig $config): array
    {
        $items = [];

        foreach (is_iterable($value) ? $value : [] as $index => $item) {
            if (count($items) >= $config->maxListItems) {
                break;
            }

            $key = $item instanceof Model && (is_int($item->getKey()) || is_string($item->getKey())) ? (string) $item->getKey() : (is_int($index) || is_string($index) ? (string) $index : (string) count($items));
            $items[] = ['key' => $key, 'item' => $item];
        }

        return $items;
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $items = $this->items($value, $config);

        return ['items' => $items, 'text' => $items === [] ? null : (string) count($items), 'title' => null, 'copy' => false];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Columns, Type::int(), default: 1, rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'))
            ->withAllowedChildren([Entry::class], 1);
    }
}
