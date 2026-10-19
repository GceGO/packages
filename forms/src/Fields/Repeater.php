<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\Contracts\FormComponent;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\State\StatePath;
use Illuminate\Support\Collection;

/**
 * A repeatable group of fields:
 *
 *     new Repeater('objectives', new TextInput('title', 'Objective'), new Textarea('description', 'Description'))
 *
 * Items are keyed by stable random keys ("i3f9c2a10b7"), so adding, removing
 * and reordering never moves another item's values or errors. Stored as a
 * list in item order. Min/MaxItems are validated on the server.
 */
class Repeater extends Field
{
    public const string MinItems = 'min_items';

    public const string MaxItems = 'max_items';

    public const string AddLabel = 'add_label';

    public const string Reorderable = 'reorderable';

    public const string Item = 'item';

    public function view(): string
    {
        return 'gcego-forms::fields.repeater';
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return array_values(array_filter($this->children()->all(), static fn (mixed $child): bool => $child instanceof Field));
    }

    public function typeRules(): array
    {
        $rules = ['array'];
        $min = $this->intProperty(self::MinItems);
        $max = $this->intProperty(self::MaxItems);

        if ($min !== null) {
            $rules[] = 'min:'.$min;
        }

        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function maxItems(): ?int
    {
        return $this->intProperty(self::MaxItems);
    }

    public function minItems(): int
    {
        return $this->intProperty(self::MinItems) ?? 0;
    }

    public function isReorderable(): bool
    {
        return $this->get(self::Reorderable) !== false;
    }

    /**
     * Stored list (or nothing) to keyed state items.
     */
    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $items = [];

        foreach (is_array($value) ? array_values($value) : [] as $item) {
            $items[StatePath::newItemKey()] = is_array($item) ? $item : [];
        }

        return $items;
    }

    public function emptyState(): mixed
    {
        $items = [];

        for ($i = 0; $i < $this->minItems(); $i++) {
            $items[StatePath::newItemKey()] = [];
        }

        return $items;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::MinItems, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'a non-negative count'),
                new PropertyDefinition(self::MaxItems, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 200, ruleDescription: 'between 1 and 200'),
                new PropertyDefinition(self::AddLabel, Type::string()),
                new PropertyDefinition(self::Reorderable, Type::bool(), default: true),
            )
            ->withAllowedChildren([FormComponent::class], min: 1);
    }
}
