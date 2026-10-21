<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\TablesConfig;

/**
 * Locale-aware numbers (intl):
 *
 *     new Number('students_count', 'Students')
 *     new Number('average', 'Average', new Property(Number::Decimals, 2))
 *
 * Integers and numeric strings are formatted as given; anything else shows
 * the placeholder. Formatting never changes the stored value.
 */
class Number extends Column
{
    public const string Decimals = 'decimals';

    /** false: no thousands separators. */
    public const string Grouping = 'grouping';

    /** A locale ("fr_CM"); null uses the application locale. */
    public const string Locale = 'locale';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    public function cellView(): string
    {
        return 'gcego-tables::columns.text';
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return is_int($value) || is_float($value) ? $value : (is_string($value) && is_numeric($value) ? $value : parent::exportValue($value, $record, $config));
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $text = Formats::number($value, $this->intProperty(self::Decimals), $this->get(self::Grouping) !== false, $this->locale());

        return $text === null ? null : ($this->stringProperty(self::Prefix) ?? '').$text.($this->stringProperty(self::Suffix) ?? '');
    }

    protected function defaultAlign(): string
    {
        return 'end';
    }

    protected function locale(): string
    {
        return $this->stringProperty(self::Locale) ?? app()->getLocale();
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Decimals, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 10, ruleDescription: 'between 0 and 10'),
            new PropertyDefinition(self::Grouping, Type::bool(), default: true),
            new PropertyDefinition(self::Locale, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $value) === 1, ruleDescription: 'a locale such as "en" or "fr_CM"'),
            new PropertyDefinition(self::Prefix, Type::string()),
            new PropertyDefinition(self::Suffix, Type::string()),
        );
    }
}
