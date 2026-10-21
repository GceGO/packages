<?php

declare(strict_types=1);

namespace Gcego\Widgets\Support;

use Carbon\CarbonInterval;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Support\Formats;
use NumberFormatter;

/**
 * KPI value formatting on top of gcego/tables' shared Formats (intl):
 * number, decimal, percent, currency, compact, duration, ratio.
 *
 * - percent: the value is a fraction (0.72 → 72%) unless PercentScale is 100;
 * - currency: any ISO 4217 code (default gcego-widgets.formatting.currency, XAF);
 * - compact: 1.2K / 3.4M (locale-aware);
 * - duration: seconds → "1h 5m";
 * - ratio: "3 : 1".
 */
final class KpiFormat
{
    public const string Format = 'format';

    public const string Decimals = 'decimals';

    public const string Currency = 'currency';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    public const string PercentScale = 'percent_scale';

    public const array Formats = ['number', 'decimal', 'percent', 'currency', 'compact', 'duration', 'ratio'];

    /**
     * @return list<PropertyDefinition>
     */
    public static function properties(string $default = 'number'): array
    {
        return [
            new PropertyDefinition(self::Format, Type::string(), default: $default, allowedValues: self::Formats),
            new PropertyDefinition(self::Decimals, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 6, ruleDescription: 'between 0 and 6'),
            new PropertyDefinition(self::Currency, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1, ruleDescription: 'an ISO 4217 code such as "XAF"'),
            new PropertyDefinition(self::Prefix, Type::string()),
            new PropertyDefinition(self::Suffix, Type::string()),
            new PropertyDefinition(self::PercentScale, Type::int(), default: 1, allowedValues: [1, 100]),
        ];
    }

    public static function format(mixed $value, string $format, ?int $decimals, string $currency, int $percentScale = 1, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $number = Formats::numeric($value);

        if ($number === null) {
            return is_string($value) && $value !== '' && $format === 'number' ? $value : null;
        }

        return match ($format) {
            'decimal' => Formats::number($number, $decimals ?? 2, true, $locale),
            'percent' => self::percent($number / $percentScale, $decimals ?? 0, $locale),
            'currency' => Formats::money(Formats::amount($number), $currency, $decimals, $locale),
            'compact' => self::compact($number, $decimals, $locale),
            'duration' => self::duration($number),
            'ratio' => Formats::number($number, $decimals ?? 1, true, $locale).' : 1',
            default => Formats::number($number, $decimals, true, $locale),
        };
    }

    private static function percent(int|float $fraction, int $decimals, string $locale): ?string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $text = $formatter->format($fraction);

        return $text === false ? null : $text;
    }

    private static function compact(int|float $number, ?int $decimals, string $locale): ?string
    {
        $style = defined(NumberFormatter::class.'::DECIMAL_COMPACT_SHORT') ? constant(NumberFormatter::class.'::DECIMAL_COMPACT_SHORT') : null;

        if (is_int($style)) {
            $formatter = new NumberFormatter($locale, $style);

            if ($decimals !== null) {
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            }

            $text = $formatter->format($number);

            return $text === false ? null : $text;
        }

        foreach ([[1e12, 'T'], [1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$size, $suffix]) {
            if (abs($number) >= $size) {
                return Formats::number($number / $size, $decimals ?? 1, true, $locale).$suffix;
            }
        }

        return Formats::number($number, $decimals, true, $locale);
    }

    private static function duration(int|float $seconds): string
    {
        return CarbonInterval::seconds((int) round(abs($seconds)))->cascade()->forHumans(['short' => true, 'parts' => 2]);
    }
}
