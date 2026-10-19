<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use BackedEnum;
use Gcego\Cli\Exceptions\GenerationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads what a model declares — table, $fillable, $hidden, casts — and,
 * when the database is reachable, the table's columns. Only the model is
 * instantiated (no query of its records, no events); a missing connection
 * or table just means fields are inferred from the model and marked so.
 *
 * Sensitive attributes are never offered: passwords, tokens, secrets,
 * two-factor columns, $hidden attributes, keys and timestamps.
 */
final class ModelInspector
{
    private const string Sensitive = '/(^|_)(password|passcode|pin|secret|token|api_key|apikey|private_key|salt|hash|two_factor|otp|recovery_codes?|remember_token|ssn|card_number|cvv)(_|$)/i';

    /**
     * @return class-string<Model>
     */
    public function model(string $class): string
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw GenerationException::missing(sprintf('--model must name an existing Eloquent model; "%s" is not one.', $class));
        }

        return $class;
    }

    /**
     * Fields worth declaring in a form, table or infolist, sensitive ones left out.
     *
     * @param  class-string<Model>  $class
     * @return array{fields: list<ModelField>, excluded: list<string>, verified: bool, table: string, key: string, title: string|null, warnings: list<string>}
     */
    public function inspect(string $class): array
    {
        $model = new $class;
        $table = $model->getTable();
        $key = $model->getKeyName();
        $casts = $model->getCasts();
        $hidden = $model->getHidden();
        $fillable = $model->getFillable();
        $warnings = [];
        $columns = $this->columns($model, $table);
        $verified = $columns !== null;
        $names = $verified ? array_keys($columns) : array_values(array_unique([...$fillable, ...array_keys($casts)]));
        $fields = [];
        $excluded = [];

        if (! $verified) {
            $warnings[] = sprintf('The table "%s" could not be read (no database connection or not migrated): fields were inferred from $fillable and casts; check them.', $table);
        }

        if ($fillable === [] && $model->getGuarded() !== []) {
            $warnings[] = sprintf('%s declares no $fillable attributes: form fields were left out (declare $fillable, then add fields). gcego/forms only saves declared fields.', $class);
        }

        foreach ($names as $name) {
            if (in_array($name, [$key, $model->getCreatedAtColumn(), $model->getUpdatedAtColumn(), 'deleted_at'], true) || str_ends_with($name, '_id') && ! in_array($name, $fillable, true)) {
                continue;
            }

            if (preg_match(self::Sensitive, $name) === 1 || in_array($name, $hidden, true)) {
                $excluded[] = $name;

                continue;
            }

            $column = $columns[$name] ?? null;
            $cast = is_string($casts[$name] ?? null) ? $casts[$name] : null;
            $enum = $cast !== null && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class) ? $cast : null;
            $type = $enum !== null ? 'enum' : $this->type($cast, is_string($column['type_name'] ?? null) ? $column['type_name'] : null);
            $fields[] = new ModelField(
                $name,
                $type,
                $verified,
                $column !== null && ($column['nullable'] ?? true) === false && ($column['default'] ?? null) === null,
                $this->length($column),
                $enum,
                in_array($name, $fillable, true),
                $this->default($column, $type, $enum),
            );
        }

        $title = null;

        foreach (['name', 'title', 'label', 'code'] as $candidate) {
            if (in_array($candidate, array_map(static fn (ModelField $field): string => $field->name, $fields), true)) {
                $title = $candidate;

                break;
            }
        }

        return ['fields' => $fields, 'excluded' => $excluded, 'verified' => $verified, 'table' => $table, 'key' => $key, 'title' => $title, 'warnings' => $warnings];
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function columns(Model $model, string $table): ?array
    {
        try {
            $schema = Schema::connection($model->getConnectionName());

            if (! $schema->hasTable($table)) {
                return null;
            }

            $columns = [];

            foreach ($schema->getColumns($table) as $column) {
                $columns[(string) $column['name']] = $column;
            }

            return $columns;
        } catch (Throwable) {
            return null;
        }
    }

    private function type(?string $cast, ?string $column): string
    {
        $cast = $cast === null ? null : strtolower(explode(':', $cast)[0]);

        return match (true) {
            in_array($cast, ['bool', 'boolean'], true) => 'boolean',
            in_array($cast, ['date', 'immutable_date'], true) => 'date',
            in_array($cast, ['datetime', 'immutable_datetime', 'timestamp'], true) => 'datetime',
            in_array($cast, ['int', 'integer'], true) => 'integer',
            in_array($cast, ['float', 'double', 'decimal', 'real'], true) => 'decimal',
            $column === null => 'string',
            in_array(strtolower($column), ['tinyint(1)', 'bool', 'boolean'], true) => 'boolean',
            in_array(strtolower($column), ['date'], true) => 'date',
            in_array(strtolower($column), ['datetime', 'timestamp', 'timestamptz'], true) => 'datetime',
            in_array(strtolower($column), ['text', 'mediumtext', 'longtext'], true) => 'text',
            in_array(strtolower($column), ['integer', 'int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int4', 'int8'], true) => 'integer',
            in_array(strtolower($column), ['decimal', 'numeric', 'float', 'double', 'real'], true) => 'decimal',
            default => 'string',
        };
    }

    /**
     * A NOT NULL column's literal default, typed (so a form does not submit null for it).
     *
     * @param  array<string, mixed>|null  $column
     * @param  class-string|null  $enum
     */
    private function default(?array $column, string $type, ?string $enum): string|int|float|bool|null
    {
        $default = $column['default'] ?? null;

        if (! is_string($default) && ! is_int($default) && ! is_float($default)) {
            return null;
        }

        $default = trim((string) $default);

        if (preg_match('/^([\'"])(.*)\1$/s', $default, $match) === 1) {
            $default = $match[2];
        } elseif (preg_match('/^-?\d+(\.\d+)?$/', $default) !== 1) {
            return null;   // an expression (CURRENT_TIMESTAMP, nextval(...)): the database computes it
        }

        return match ($type) {
            'boolean' => in_array(strtolower($default), ['1', 'true'], true),
            'integer' => is_numeric($default) ? (int) $default : null,
            'decimal' => is_numeric($default) ? (float) $default : null,
            'enum' => $enum !== null && is_subclass_of($enum, BackedEnum::class) && $enum::tryFrom($default) !== null ? $default : null,
            'date', 'datetime' => null,
            default => $default,
        };
    }

    /**
     * @param  array<string, mixed>|null  $column
     */
    private function length(?array $column): ?int
    {
        $type = is_string($column['type'] ?? null) ? $column['type'] : '';

        return preg_match('/\((\d+)\)/', $type, $match) === 1 ? (int) $match[1] : null;
    }
}
