<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Carbon\CarbonImmutable;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;

/**
 * Instants shown in the display zone (Timezone, then formatting.timezone, then app.timezone):
 *
 *     new DateTime('created_at', 'Created At')
 *     new DateTime('published_at', new Property(DateTime::Timezone, 'Africa/Douala'))
 */
class DateTime extends Date
{
    public const string Timezone = 'timezone';

    public function moment(mixed $value, InfolistsConfig $config): ?CarbonImmutable
    {
        $app = config('app.timezone', 'UTC');

        return Formats::moment($value, $this->stringProperty(self::Timezone) ?? $config->timezone ?? (is_string($app) ? $app : 'UTC'));
    }

    protected function defaultFormat(InfolistsConfig $config): ?string
    {
        return $config->dateTimeFormat;
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
