<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Carbon\CarbonImmutable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\TablesConfig;

/**
 * Instants, shown in the display time zone:
 *
 *     new DateTime('published_at', 'Published')                                    // "Mar 1, 2026 10:30 AM"
 *     new DateTime('published_at', new Property(DateTime::Timezone, 'Africa/Douala'))
 *
 * Stored values are read in app.timezone and converted on an immutable copy
 * (Timezone property, then gcego-tables.dates.timezone, then app.timezone).
 */
class DateTime extends Date
{
    public const string Timezone = 'timezone';

    protected function shift(CarbonImmutable $date, TablesConfig $config): CarbonImmutable
    {
        return $date->setTimezone($this->stringProperty(self::Timezone) ?? $config->timezone ?? $this->storageZone());
    }

    protected function isoFormat(): string
    {
        return 'lll';
    }

    protected function machineFormat(): string
    {
        return 'Y-m-d\TH:i:sP';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Timezone, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && in_array($value, timezone_identifiers_list(), true), ruleDescription: 'a timezone identifier'),
        );
    }
}
