<?php

declare(strict_types=1);

namespace Gcego\Data\Filters;

use BackedEnum;
use Gcego\Data\Contracts\SourceConfiguration;
use Gcego\Data\Exceptions\InvalidFilterException;
use Gcego\Data\Exceptions\InvalidSourceException;
use Gcego\Data\Support\FieldName;

/**
 * Allows request input to filter one field:
 *
 *     new Filterable('status', options: ['draft', 'published'])
 *     new Filterable('score', [FilterOperator::GreaterThanOrEqual, FilterOperator::LessThanOrEqual], ValueType::Integer)
 *     new Filterable('subject', column: 'subject_id', type: ValueType::Integer)
 *     new Filterable('topic', column: 'topics.name')   // Eloquent: whereHas('topics')
 *
 * The request names the key ("status"); the column comes only from this
 * trusted definition. Values are validated and cast with $type, restricted to
 * $options when given, and always bound as parameters.
 */
final readonly class Filterable implements SourceConfiguration
{
    public const string KeyPattern = '/^[a-z][a-z0-9_]{0,63}(?:\.[a-z][a-z0-9_]{0,63}){0,3}$/';

    /**
     * @var list<FilterOperator>
     */
    public array $operators;

    public string $column;

    /**
     * @var list<string|int|float|bool>
     */
    public array $options;

    /**
     * @param  FilterOperator|list<FilterOperator>  $operators
     * @param  list<scalar|BackedEnum>  $options  Allowed values (after casting). Empty allows any valid value.
     *
     * @throws InvalidSourceException
     */
    public function __construct(
        public string $key,
        FilterOperator|array $operators = [FilterOperator::Equals, FilterOperator::In],
        public ValueType $type = ValueType::String,
        ?string $column = null,
        array $options = [],
        public ?string $label = null,
    ) {
        if (preg_match(self::KeyPattern, $key) !== 1) {
            throw InvalidSourceException::invalidDefinition('Filterable "'.mb_substr($key, 0, 64).'"', 'keys use lowercase letters, digits and "_", separated by ".".');
        }

        $operators = $operators instanceof FilterOperator ? [$operators] : $operators;

        if ($operators === [] || array_filter($operators, static fn (mixed $operator): bool => ! $operator instanceof FilterOperator) !== []) {
            throw InvalidSourceException::invalidDefinition('Filterable "'.$key.'"', 'operators must be a non-empty list of FilterOperator cases.');
        }

        $this->operators = array_values(array_unique($operators, SORT_REGULAR));
        $this->column = FieldName::assert('Filterable', $column ?? $key);
        try {
            $this->options = array_map(static fn (mixed $option): string|int|float|bool => $type->cast($key, $option), array_values($options));
        } catch (InvalidFilterException) {
            throw InvalidSourceException::invalidDefinition('Filterable "'.$key.'"', sprintf('every option must be a valid %s.', $type->describe()));
        }
    }

    public function allows(FilterOperator $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }

    /**
     * @return array{key: string, column: string, type: string, operators: list<string>, options: list<string|int|float|bool>, label: string|null}
     */
    public function normalize(): array
    {
        return [
            'key' => $this->key,
            'column' => $this->column,
            'type' => $this->type->value,
            'operators' => array_map(static fn (FilterOperator $operator): string => $operator->value, $this->operators),
            'options' => $this->options,
            'label' => $this->label,
        ];
    }
}
