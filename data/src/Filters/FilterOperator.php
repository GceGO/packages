<?php

declare(strict_types=1);

namespace Gcego\Data\Filters;

/**
 * Supported filter operators. Values are the request names: filter[score][gte]=50.
 */
enum FilterOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case In = 'in';
    case NotIn = 'not_in';
    case Between = 'between';
    case Null = 'null';
    case NotNull = 'not_null';

    /**
     * Number of values: 1 (scalar), -1 (non-empty list), 2 (pair) or 0 (none).
     */
    public function arity(): int
    {
        return match ($this) {
            self::In, self::NotIn => -1,
            self::Between => 2,
            self::Null, self::NotNull => 0,
            default => 1,
        };
    }

    /**
     * The SQL comparison operator of single-value operators.
     */
    public function comparison(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '!=',
            self::GreaterThan => '>',
            self::GreaterThanOrEqual => '>=',
            self::LessThan => '<',
            self::LessThanOrEqual => '<=',
            default => throw new \LogicException(sprintf('Operator "%s" is not a comparison.', $this->value)),
        };
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $operator): string => $operator->value, self::cases());
    }
}
