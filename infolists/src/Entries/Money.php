<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;

/**
 * Currency amounts in any ISO 4217 currency (default XAF):
 *
 *     new Money('fee', 'Fee')                                                                       // "FCFA 25,000"
 *     new Money('balance_cents', 'Balance', new Property(Money::Currency, 'EUR'), new Property(Money::MinorUnits, true))
 *
 * Display only: minor units are converted with bcmath; no arithmetic happens here.
 */
class Money extends Entry
{
    public const string Currency = 'currency';

    public const string MinorUnits = 'minor_units';

    public const string Decimals = 'decimals';

    public const string Locale = 'locale';

    public function view(): string
    {
        return 'gcego-infolists::entries.text';
    }

    public function currency(): string
    {
        return $this->stringProperty(self::Currency) ?? 'XAF';
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $digits = $this->intProperty(self::Decimals) ?? Formats::currencyDigits($this->currency());

        return Formats::money(Formats::amount($value, $this->get(self::MinorUnits) === true, $digits), $this->currency(), $this->intProperty(self::Decimals), $this->stringProperty(self::Locale));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Currency, Type::string(), default: 'XAF', rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1, ruleDescription: 'an ISO 4217 currency code such as "XAF"'),
            new PropertyDefinition(self::MinorUnits, Type::bool(), default: false),
            new PropertyDefinition(self::Decimals, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 6, ruleDescription: 'between 0 and 6'),
            new PropertyDefinition(self::Locale, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $value) === 1, ruleDescription: 'a locale'),
        );
    }
}
