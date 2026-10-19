<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

use ArrayAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Reads a column's value from a row by a trusted, definition-time path
 * ("name", "category.name", "tags.label").
 *
 * - Eloquent: attributes and casts through getAttribute(); relation
 *   segments are followed only when the relation is already loaded (the
 *   table eager-loads every relation its columns use), so a cell never
 *   triggers a query; to-many relations yield a list of values.
 * - Attributes in the model's $hidden are never read.
 * - Arrays, ArrayAccess and plain objects (query builder rows) by key.
 *
 * Paths come from column definitions only, never from the browser, and no
 * method is ever called by name.
 */
final class ValueResolver
{
    public static function get(mixed $record, string $path): mixed
    {
        return self::walk($record, explode('.', $path));
    }

    /**
     * @param  list<string>  $segments
     */
    private static function walk(mixed $current, array $segments): mixed
    {
        foreach ($segments as $index => $segment) {
            $last = $index === count($segments) - 1;

            if ($current instanceof Collection) {
                $rest = array_slice($segments, $index);
                $values = [];

                foreach ($current as $item) {
                    $value = self::walk($item, $rest);

                    foreach (is_array($value) ? $value : [$value] as $one) {
                        if ($one !== null) {
                            $values[] = $one;
                        }
                    }
                }

                return $values;
            }

            $current = match (true) {
                $current instanceof Model => self::fromModel($current, $segment, $last),
                is_array($current), $current instanceof ArrayAccess => $current[$segment] ?? null,
                is_object($current) => get_object_vars($current)[$segment] ?? null,
                default => null,
            };

            if ($current === null) {
                return null;
            }
        }

        return $current;
    }

    private static function fromModel(Model $model, string $segment, bool $last): mixed
    {
        if (in_array($segment, $model->getHidden(), true)) {
            return null;
        }

        if ($model->relationLoaded($segment)) {
            return $model->getRelation($segment);
        }

        if (! $last || $model->isRelation($segment)) {
            // An unloaded relation: never lazy-load from a cell.
            return null;
        }

        return $model->getAttribute($segment);
    }
}
