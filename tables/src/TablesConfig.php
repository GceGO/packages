<?php

declare(strict_types=1);

namespace Gcego\Tables;

use Gcego\Tables\Exceptions\InvalidTableException;

/**
 * Validated gcego-tables configuration (config/gcego-tables.php).
 */
final readonly class TablesConfig
{
    /**
     * @param  list<int>  $perPageOptions
     */
    public function __construct(
        public int $defaultPerPage = 25,
        public int $maxPerPage = 100,
        public array $perPageOptions = [10, 25, 50, 100],
        public int $searchDebounceMs = 350,
        public int $searchMaxLength = 100,
        public int $maxSelection = 500,
        public bool $preferences = true,
        public bool $savedViews = true,
        public int $maxSavedViews = 25,
        public string $preferencesTable = 'gcego_table_preferences',
        public string $viewsTable = 'gcego_table_views',
        public ?string $connection = null,
        public ?string $timezone = null,
        public int $exportChunk = 100,
        public int $exportMaxRows = 10000,
    ) {
        $check = static function (bool $valid, string $key, string $reason): void {
            if (! $valid) {
                throw InvalidTableException::invalid('gcego-tables.'.$key, $reason);
            }
        };

        $check($maxPerPage >= 1 && $maxPerPage <= 500, 'pagination.max_per_page', 'use 1 to 500.');
        $check($defaultPerPage >= 1 && $defaultPerPage <= $maxPerPage, 'pagination.default_per_page', 'use 1 to pagination.max_per_page.');
        $check($perPageOptions !== [] && array_filter($perPageOptions, static fn (int $size): bool => $size < 1 || $size > $maxPerPage) === [], 'pagination.options', 'use page sizes between 1 and pagination.max_per_page.');
        $check($searchDebounceMs >= 0 && $searchDebounceMs <= 5000, 'search.debounce_ms', 'use 0 to 5000.');
        $check($searchMaxLength >= 1 && $searchMaxLength <= 200, 'search.max_length', 'use 1 to 200 (gcego/data caps search at 200).');
        $check($maxSelection >= 1 && $maxSelection <= 10000, 'selection.max_records', 'use 1 to 10000.');
        $check($maxSavedViews >= 1 && $maxSavedViews <= 200, 'saved_views.max_per_table', 'use 1 to 200.');
        $check($exportChunk >= 10 && $exportChunk <= 5000, 'export.chunk', 'use 10 to 5000.');
        $check($exportMaxRows >= 1 && $exportMaxRows <= 1000000, 'export.max_rows', 'use 1 to 1000000.');

        foreach (['storage.preferences_table' => $preferencesTable, 'storage.views_table' => $viewsTable] as $key => $table) {
            $check(preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table) === 1, $key, 'use a lowercase table name.');
        }

        $check($timezone === null || in_array($timezone, timezone_identifiers_list(), true), 'dates.timezone', 'use a valid timezone identifier or null.');
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $int = static function (string $key, int $default) use ($config): int {
            $value = data_get($config, $key, $default);

            return is_int($value) ? $value : throw InvalidTableException::invalid('gcego-tables.'.$key, 'it must be an integer.');
        };
        $bool = static function (string $key, bool $default) use ($config): bool {
            $value = data_get($config, $key, $default);

            return is_bool($value) ? $value : throw InvalidTableException::invalid('gcego-tables.'.$key, 'it must be a boolean.');
        };
        $string = static function (string $key, ?string $default) use ($config): ?string {
            $value = data_get($config, $key, $default);

            return $value === null || is_string($value) ? $value : throw InvalidTableException::invalid('gcego-tables.'.$key, 'it must be a string.');
        };
        $options = data_get($config, 'pagination.options', [10, 25, 50, 100]);

        if (! is_array($options) || array_filter($options, static fn (mixed $size): bool => ! is_int($size)) !== []) {
            throw InvalidTableException::invalid('gcego-tables.pagination.options', 'it must be a list of integers.');
        }

        $options = array_values(array_unique(array_filter($options, is_int(...))));
        sort($options);

        return new self(
            defaultPerPage: $int('pagination.default_per_page', 25),
            maxPerPage: $int('pagination.max_per_page', 100),
            perPageOptions: $options,
            searchDebounceMs: $int('search.debounce_ms', 350),
            searchMaxLength: $int('search.max_length', 100),
            maxSelection: $int('selection.max_records', 500),
            preferences: $bool('preferences.enabled', true),
            savedViews: $bool('saved_views.enabled', true),
            maxSavedViews: $int('saved_views.max_per_table', 25),
            preferencesTable: $string('storage.preferences_table', 'gcego_table_preferences') ?? 'gcego_table_preferences',
            viewsTable: $string('storage.views_table', 'gcego_table_views') ?? 'gcego_table_views',
            connection: $string('storage.connection', null),
            timezone: $string('dates.timezone', null),
            exportChunk: $int('export.chunk', 100),
            exportMaxRows: $int('export.max_rows', 10000),
        );
    }
}
