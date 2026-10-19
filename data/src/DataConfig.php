<?php

declare(strict_types=1);

namespace Gcego\Data;

use Gcego\Data\Exceptions\InvalidSourceException;

/**
 * Validated gcego-data configuration.
 */
final readonly class DataConfig
{
    public function __construct(
        public int $defaultPerPage = 25,
        public int $maxPerPage = 100,
        public int $searchMaxLength = 200,
        public int $searchMaxTerms = 8,
        public int $filterMaxValues = 100,
        public int $sortMaxFields = 3,
        public bool $stableOrdering = true,
        public bool $strict = true,
        public string $ability = 'gcego-data.query',
    ) {
        foreach (['defaultPerPage' => $defaultPerPage, 'maxPerPage' => $maxPerPage, 'searchMaxLength' => $searchMaxLength, 'searchMaxTerms' => $searchMaxTerms, 'filterMaxValues' => $filterMaxValues, 'sortMaxFields' => $sortMaxFields] as $name => $value) {
            if ($value < 1) {
                throw InvalidSourceException::invalidDefinition('gcego-data configuration', sprintf('%s must be a positive integer.', $name));
            }
        }

        if ($defaultPerPage > $maxPerPage) {
            throw InvalidSourceException::invalidDefinition('gcego-data configuration', 'pagination.default_per_page must not exceed pagination.max_per_page.');
        }

        if (trim($ability) === '') {
            throw InvalidSourceException::invalidDefinition('gcego-data configuration', 'authorization.ability must not be empty.');
        }
    }

    /**
     * @param  array<mixed>  $config  The gcego-data configuration array.
     */
    public static function fromArray(array $config): self
    {
        $int = static function (string $path, int $default) use ($config): int {
            $value = data_get($config, $path, $default);

            return is_int($value) ? $value : throw InvalidSourceException::invalidDefinition('gcego-data configuration', sprintf('%s must be an integer.', $path));
        };
        $bool = static function (string $path, bool $default) use ($config): bool {
            $value = data_get($config, $path, $default);

            return is_bool($value) ? $value : throw InvalidSourceException::invalidDefinition('gcego-data configuration', sprintf('%s must be a boolean.', $path));
        };
        $ability = data_get($config, 'authorization.ability', 'gcego-data.query');

        return new self(
            defaultPerPage: $int('pagination.default_per_page', 25),
            maxPerPage: $int('pagination.max_per_page', 100),
            searchMaxLength: $int('search.max_length', 200),
            searchMaxTerms: $int('search.max_terms', 8),
            filterMaxValues: $int('filters.max_values', 100),
            sortMaxFields: $int('sorting.max_fields', 3),
            stableOrdering: $bool('sorting.stable', true),
            strict: $bool('queries.strict', true),
            ability: is_string($ability) ? $ability : throw InvalidSourceException::invalidDefinition('gcego-data configuration', 'authorization.ability must be a string.'),
        );
    }
}
