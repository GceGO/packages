<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\TablesConfig;

/**
 * Locale-aware currency amounts in any ISO 4217 currency (intl):
 *
 *     new Money('fee', 'Fee', new Property(Money::Currency, 'XAF'))                       // "FCFA 25,000"
 *     new Money('balance_cents', 'Balance', new Property(Money::Currency, 'EUR'), new Property(Money::MinorUnits, true))
 *
 * Values are decimal strings ("1234.50"), integers or floats. With
 * MinorUnits the stored integer is in the currency's smallest unit and is
 * converted with string arithmetic (bcmath), never with floats. Display only:
 * do not use formatted amounts for calculations.
 */
class Money extends Column
{
    public const string Currency = 'currency';

    /** Stored values are integers in the smallest unit (cents). */
    public const string MinorUnits = 'minor_units';

    /** Fraction digits shown; null uses the currency's own (0 for XAF, 2 for EUR). */
    public const string Decimals = 'decimals';

    public const string Locale = 'locale';

    public function cellView(): string
    {
        return 'gcego-tables::columns.text';
    }

    public function currency(): string
    {
        return $this->stringProperty(self::Currency) ?? 'XAF';
    }

    /**
     * The amount as a decimal string ("-1234.50"), or null when the value is not an amount.
     */
    public function amount(mixed $value): ?string
    {
        return Formats::amount($value, $this->get(self::MinorUnits) === true, $this->fractionDigits());
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return $this->amount($value);
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        return Formats::money($this->amount($value), $this->currency(), $this->intProperty(self::Decimals), $this->stringProperty(self::Locale));
    }

    protected function defaultAlign(): string
    {
        return 'end';
    }

    /**
     * The currency's minor-unit digits (ICU data), or the Decimals property.
     */
    public function fractionDigits(): int
    {
        return $this->intProperty(self::Decimals) ?? Formats::currencyDigits($this->currency());
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Currency, Type::string(), default: 'XAF', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1, ruleDescription: 'an ISO 4217 currency code such as "XAF"'),
            new PropertyDefinition(self::MinorUnits, Type::bool(), default: false),
            new PropertyDefinition(self::Decimals, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 6, ruleDescription: 'between 0 and 6'),
            new PropertyDefinition(self::Locale, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $value) === 1, ruleDescription: 'a locale such as "en" or "fr_CM"'),
        );
    }
}
