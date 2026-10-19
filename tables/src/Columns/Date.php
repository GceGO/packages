<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Carbon\CarbonImmutable;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\TablesConfig;

/**
 * Calendar dates, never shifted by time zones:
 *
 *     new Date('starts_on', 'Starts')                                  // "Mar 1, 2026" (locale-aware)
 *     new Date('starts_on', new Property(Date::Format, 'd/m/Y'))
 *     new Date('starts_on', new Property(Date::Relative, true))        // "3 days ago"
 *
 * Formatting works on an immutable copy: the record's value is never changed.
 * Sorting uses the stored column, not the displayed text.
 */
class Date extends Column
{
    /** A PHP date format; null uses the locale's medium format. */
    public const string Format = 'format';

    public const string Relative = 'relative';

    public function cellView(): string
    {
        return 'gcego-tables::columns.text';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $date = $this->moment($value, $config);

        return [...parent::cellData($value, $record, $config), 'datetime' => $date?->format($this->machineFormat())];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return $this->moment($value, $config)?->format($this->machineFormat());
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $date = $this->moment($value, $config);

        return $date === null ? null : Formats::date($date, $this->stringProperty(self::Format), $this->get(self::Relative) === true, $this->isoFormat());
    }

    /**
     * An immutable instant in the display zone (dates stay on their calendar day).
     */
    public function moment(mixed $value, TablesConfig $config): ?CarbonImmutable
    {
        $date = Formats::moment($value);

        return $date === null ? null : $this->shift($date, $config);
    }

    protected function shift(CarbonImmutable $date, TablesConfig $config): CarbonImmutable
    {
        return $date;
    }

    protected function isoFormat(): string
    {
        return 'll';
    }

    protected function machineFormat(): string
    {
        return 'Y-m-d';
    }

    protected function storageZone(): string
    {
        $zone = config('app.timezone', 'UTC');

        return is_string($zone) ? $zone : 'UTC';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Format, Type::string()),
            new PropertyDefinition(self::Relative, Type::bool(), default: false),
        );
    }
}
