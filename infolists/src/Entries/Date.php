<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Carbon\CarbonImmutable;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;

/**
 * Calendar dates, never shifted by time zones, inside <time datetime="…">:
 *
 *     new Date('starts_on', 'Starts')                                     // locale medium format, or formatting.date_format
 *     new Date('starts_on', new Property(Date::Format, 'd/m/Y'))
 *     new Date('due_on', new Property(Date::Relative, true))             // "in 3 days"
 *
 * Formatting works on an immutable copy; the record is never changed.
 */
class Date extends Entry
{
    public const string Format = 'format';

    public const string Relative = 'relative';

    public function view(): string
    {
        return 'gcego-infolists::entries.text';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $moment = $this->moment($value, $config);

        return [...parent::display($value, $record, $config), 'datetime' => $moment?->format($this->machineFormat())];
    }

    public function moment(mixed $value, InfolistsConfig $config): ?CarbonImmutable
    {
        return Formats::moment($value);
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $moment = $this->moment($value, $config);

        return $moment === null ? null : Formats::date($moment, $this->stringProperty(self::Format) ?? $this->defaultFormat($config), $this->get(self::Relative) === true, $this->isoFormat());
    }

    protected function defaultFormat(InfolistsConfig $config): ?string
    {
        return $config->dateFormat;
    }

    protected function isoFormat(): string
    {
        return 'll';
    }

    protected function machineFormat(): string
    {
        return 'Y-m-d';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Format, Type::string()),
            new PropertyDefinition(self::Relative, Type::bool(), default: false),
        );
    }
}
