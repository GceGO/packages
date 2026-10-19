<?php

declare(strict_types=1);

namespace Gcego\Forms\Reactive;

use Gcego\Forms\Schema\FormSchema;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StatePath;

/**
 * Evaluates the trusted conditions of a schema against a state, server-side:
 * visibility (field, layouts and repeaters), requiredness and disabled state,
 * and expands repeaters into concrete field instances.
 *
 * Evaluation order is the schema order; dependencies are acyclic (checked
 * when the schema is compiled), so results never depend on evaluation order.
 */
final class ConditionEvaluator
{
    /**
     * Visible field instances for the state, in schema order. Children of
     * invisible repeaters are not included.
     *
     * @return list<FieldInstance>
     */
    public function instances(FormSchema $schema, FormState $state, bool $visibleOnly = true): array
    {
        return $this->expand($schema, $state, null, [], $visibleOnly);
    }

    public function visible(FieldInstance $instance, FormState $state): bool
    {
        if ($instance->entry->field->isHidden()) {
            return false;
        }

        foreach ($instance->entry->visibility as ['condition' => $condition, 'scope' => $scope]) {
            $item = $scope === null ? null : $this->itemPathFor($scope, $instance->keys);

            if (! $condition->condition->passes($state, $item, $scope === $instance->entry->repeater ? $instance->entry->siblings : [])) {
                return false;
            }
        }

        return true;
    }

    public function required(FieldInstance $instance, FormState $state): bool
    {
        if ($instance->entry->field->isRequired()) {
            return true;
        }

        foreach ($instance->entry->required as $condition) {
            if ($condition->condition->passes($state, $instance->itemPath(), $instance->entry->siblings)) {
                return true;
            }
        }

        return false;
    }

    public function disabled(FieldInstance $instance, FormState $state): bool
    {
        if ($instance->entry->field->isDisabled() || $instance->entry->field->isReadonly()) {
            return true;
        }

        foreach ($instance->entry->disabled as $condition) {
            if ($condition->condition->passes($state, $instance->itemPath(), $instance->entry->siblings)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Item keys of a repeater instance in state order (only well-formed keys).
     *
     * @return list<string>
     */
    public function itemKeys(FormState $state, string $path): array
    {
        $items = $state->get($path);

        return is_array($items) ? array_values(array_filter(array_map(strval(...), array_keys($items)), StatePath::isItemKey(...))) : [];
    }

    /**
     * @param  list<string>  $keys
     * @return list<FieldInstance>
     */
    private function expand(FormSchema $schema, FormState $state, ?string $repeater, array $keys, bool $visibleOnly): array
    {
        $instances = [];

        foreach ($schema->children($repeater) as $entry) {
            $instance = new FieldInstance($entry, $entry->concrete($keys), $keys);

            if ($visibleOnly && ! $this->visible($instance, $state)) {
                continue;
            }

            $instances[] = $instance;

            if ($entry->isRepeater()) {
                foreach ($this->itemKeys($state, $instance->path) as $key) {
                    array_push($instances, ...$this->expand($schema, $state, $entry->path, [...$keys, $key], $visibleOnly));
                }
            }
        }

        return $instances;
    }

    /**
     * The concrete item path of a repeater template for the given keys ("objectives" + ["i1"] => "objectives.i1").
     *
     * @param  list<string>  $keys
     */
    public function itemPathFor(string $scope, array $keys): ?string
    {
        $depth = substr_count($scope, '*') + 1;

        if (count($keys) < $depth) {
            return null;
        }

        $segments = explode('.', $scope);
        $index = 0;

        foreach ($segments as $position => $segment) {
            if ($segment === '*') {
                $segments[$position] = $keys[$index++];
            }
        }

        return implode('.', $segments).'.'.$keys[$depth - 1];
    }
}
