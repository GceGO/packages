<?php

declare(strict_types=1);

namespace Gcego\Analytics\Filters;

enum FilterOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case Between = 'between';
    case IsNull = 'null';
    case NotNull = 'not_null';

    public function sql(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '!=',
            self::GreaterThan => '>',
            self::GreaterOrEqual => '>=',
            self::LessThan => '<',
            self::LessOrEqual => '<=',
            default => '=',
        };
    }

    public function takesList(): bool
    {
        return in_array($this, [self::In, self::NotIn, self::Between], true);
    }

    public function takesNoValue(): bool
    {
        return $this === self::IsNull || $this === self::NotNull;
    }
}
