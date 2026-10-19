<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Forms\Building\ChoiceFieldBuilder;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Configuration\Relationship;
use Gcego\Forms\FormsConfig;
use Illuminate\Support\Collection;

/**
 * Fields whose values must be among trusted options (Options or Relationship nodes).
 */
abstract class ChoiceField extends Field
{
    public function isMultiple(): bool
    {
        return false;
    }

    public function options(): ?Options
    {
        $node = $this->configurations()->first(Options::class);

        return $node instanceof Options ? $node : null;
    }

    public function relationship(): ?Relationship
    {
        $node = $this->configurations()->first(Relationship::class);

        return $node instanceof Relationship ? $node : null;
    }

    public function typeRules(): array
    {
        return $this->isMultiple() ? ['array'] : [];
    }

    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        if ($this->isMultiple()) {
            $values = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

            return array_values(array_unique(array_filter($values, static fn (mixed $item): bool => is_int($item) || (is_string($item) && $item !== ''))));
        }

        return is_string($value) && $value === '' ? null : $value;
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        if ($this->isMultiple() && $value instanceof Collection) {
            return $value->all();
        }

        return parent::hydrate($value, $config);
    }

    public static function builderClass(): string
    {
        return ChoiceFieldBuilder::class;
    }
}
