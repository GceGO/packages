<?php

declare(strict_types=1);

namespace Gcego\Analytics\Datasets;

use Gcego\Analytics\Measures\Aggregate;

enum FieldType: string
{
    case Number = 'number';
    case Integer = 'integer';
    case String = 'string';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';

    /**
     * Aggregates that make sense for the type (a field may allow fewer).
     *
     * @return list<Aggregate>
     */
    public function aggregates(): array
    {
        return match ($this) {
            self::Number, self::Integer => Aggregate::cases(),
            self::Date, self::DateTime => [Aggregate::Count, Aggregate::CountDistinct, Aggregate::Minimum, Aggregate::Maximum],
            self::String, self::Boolean => [Aggregate::Count, Aggregate::CountDistinct],
        };
    }

    public function isTemporal(): bool
    {
        return $this === self::Date || $this === self::DateTime;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
