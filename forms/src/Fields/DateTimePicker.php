<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\FormsConfig;
use Throwable;

/**
 * A date and time (native <input type="datetime-local">).
 *
 * Users enter and see local time in the display time zone (the field's
 * Timezone, else gcego-forms.dates.timezone, else app.timezone); values are
 * stored as "Y-m-d H:i:s" in app.timezone. Min and Max are in display time.
 */
class DateTimePicker extends DatePicker
{
    public const string Timezone = 'timezone';

    public function inputType(): string
    {
        return 'datetime-local';
    }

    public function format(): string
    {
        return 'Y-m-d\TH:i';
    }

    public function displayTimezone(FormsConfig $config): string
    {
        $app = config('app.timezone', 'UTC');
        $zone = $this->stringProperty(self::Timezone) ?? $config->timezone ?? (is_string($app) ? $app : 'UTC');

        return in_array($zone, \DateTimeZone::listIdentifiers(), true) || $zone === 'UTC' ? $zone : 'UTC';
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        if (is_string($value) && $value !== '') {
            try {
                $value = CarbonImmutable::parse($value, self::storageZone());
            } catch (Throwable) {
                return null;
            }
        }

        return $value instanceof DateTimeInterface ? CarbonImmutable::instance($value)->setTimezone($this->displayTimezone($config))->format($this->format()) : $value;
    }

    public function dehydrate(mixed $value, FormsConfig $config): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $local = CarbonImmutable::createFromFormat('!'.$this->format(), $value, $this->displayTimezone($config));

        return $local === null ? null : $local->setTimezone(self::storageZone())->format('Y-m-d H:i:s');
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Timezone, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && ($value === 'UTC' || in_array($value, \DateTimeZone::listIdentifiers(), true)), ruleDescription: 'a time zone identifier'),
        );
    }

    protected static function boundFormat(): string
    {
        return 'Y-m-d\TH:i';
    }

    private static function storageZone(): string
    {
        $zone = config('app.timezone', 'UTC');

        return is_string($zone) ? $zone : 'UTC';
    }
}
