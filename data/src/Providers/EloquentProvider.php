<?php

declare(strict_types=1);

namespace Gcego\Data\Providers;

use Gcego\Data\Contracts\SupportsRelationships;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Exceptions\InvalidSourceException;
use Gcego\Data\Relationships\RelationPathValidator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionClass;

/**
 * Eloquent models: $model->newQuery() keeps global scopes (tenancy, soft
 * deletes, ...) and the model's connection unless Source::Connection is set.
 * Keys follow the model: integer, UUID, ULID or custom primary keys.
 * Records are model instances, so $hidden, $visible, casts and accessors apply.
 */
final class EloquentProvider extends BuilderProvider implements SupportsRelationships
{
    protected function validate(SourceDefinition $source): void
    {
        $model = $source->model;

        if ($model === null || ! is_subclass_of($model, Model::class) || (new ReflectionClass($model))->isAbstract()) {
            throw InvalidSourceException::invalidModel((string) $model);
        }

        if ($source->columns !== null) {
            throw InvalidSourceException::invalid($source->name, 'Eloquent sources do not select columns; use the model\'s $hidden/$visible or Transform::only().');
        }

        $paths = [...array_keys($source->with), ...array_keys($source->includables)];

        foreach ([...array_map(static fn ($filterable): string => $filterable->column, array_values($source->filterables)), ...array_map(static fn ($filter): string => $filter->field, $source->filters), ...($source->search->fields ?? [])] as $field) {
            if (str_contains($field, '.')) {
                $paths[] = substr($field, 0, (int) strrpos($field, '.'));
            }
        }

        foreach (array_unique($paths) as $path) {
            RelationPathValidator::assert($source->name, $model, $path);
        }
    }

    protected function newQuery(SourceDefinition $source): EloquentBuilder
    {
        /** @var class-string<Model> $class */
        $class = $source->model;
        $model = new $class;

        if ($source->connection !== null) {
            $model->setConnection($source->connection);
        }

        return $model->newQuery();
    }

    protected function keyColumn(EloquentBuilder|QueryBuilder $query, SourceDefinition $source): string
    {
        return $query instanceof EloquentBuilder ? $query->getModel()->getQualifiedKeyName() : 'id';
    }

    protected function normalizeKey(SourceDefinition $source, string|int $key): string|int|null
    {
        /** @var class-string<Model> $class */
        $class = $source->model;
        $model = new $class;

        if (in_array($model->getKeyType(), ['int', 'integer'], true)) {
            return is_int($key) ? $key : (preg_match('/^-?\d{1,18}$/', $key) === 1 ? (int) $key : null);
        }

        $key = (string) $key;

        if (in_array(HasUniqueStringIds::class, class_uses_recursive($model), true)) {
            // HasUuids/HasUlids decide what a valid key looks like; the method is protected.
            $valid = (fn (string $value): mixed => $this->isValidUniqueId($value))->call($model, $key); // @phpstan-ignore method.notFound (protected method of HasUniqueStringIds, which the check above guarantees)

            return $valid === true ? $key : null;
        }

        return $key !== '' && strlen($key) <= 255 && preg_match('/[\x00-\x1F\x7F]/', $key) !== 1 ? $key : null;
    }

    protected function load(EloquentBuilder|QueryBuilder $query, array $relations): void
    {
        if (! $query instanceof EloquentBuilder || $relations === []) {
            return;
        }

        $with = [];

        foreach ($relations as $path => $constraint) {
            $constraint === null ? $with[] = $path : $with[$path] = $constraint;
        }

        $query->with($with);
    }
}
