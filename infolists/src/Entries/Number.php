<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;

/**
 * Locale-aware numbers (intl NumberFormatter, shared with gcego/tables):
 *
 *     new Number('students_count', 'Students')
 *     new Number('average', 'Average', new Property(Number::Decimals, 2), new Property(Number::Suffix, ' / 20'))
 */
class Number extends Entry
{
    public const string Decimals = 'decimals';

    public const string Grouping = 'grouping';

    public const string Locale = 'locale';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    public function view(): string
    {
        return 'gcego-infolists::entries.text';
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $text = Formats::number($value, $this->intProperty(self::Decimals), $this->get(self::Grouping) !== false, $this->stringProperty(self::Locale));

        return $text === null ? null : ($this->stringProperty(self::Prefix) ?? '').$text.($this->stringProperty(self::Suffix) ?? '');
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
