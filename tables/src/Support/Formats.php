<?php

declare(strict_types=1);

namespace Gcego\Tables\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use NumberFormatter;
use Throwable;

/**
 * Display formatting shared by table columns and infolist entries, on top
 * of PHP's intl (NumberFormatter) and Carbon. Presentation only: nothing
 * here changes a stored value, and money is never used for arithmetic.
 */
final class Formats
{
    /**
     * Any value as plain text: enums by label() or value, lists joined, dates in ISO form; null for empty values.
     */
    public static function text(mixed $value): ?string
    {
        $text = match (true) {
            $value === null => null,
            is_bool($value) => self::translate($value ? 'gcego-tables::messages.yes' : 'gcego-tables::messages.no'),
            is_scalar($value) => (string) $value,
            $value instanceof \BackedEnum => method_exists($value, 'label') && is_scalar($label = $value->label()) ? (string) $label : (string) $value->value,
            $value instanceof \UnitEnum => method_exists($value, 'label') && is_scalar($label = $value->label()) ? (string) $label : $value->name,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \Stringable => (string) $value,
            is_iterable($value) => implode(', ', array_filter(array_map(self::text(...), is_array($value) ? $value : iterator_to_array($value, false)), static fn (?string $item): bool => $item !== null && $item !== '')),
            default => null,
        };

        return $text === null || $text === '' ? null : $text;
    }

    public static function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    /**
     * Ints stay ints; numeric strings become int or float, for display only.
     */
    public static function numeric(mixed $value): int|float|null
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => is_finite($value) ? $value : null,
            is_string($value) && preg_match('/^-?\d{1,18}$/', trim($value)) === 1 => (int) trim($value),
            is_string($value) && is_numeric(trim($value)) => (float) trim($value),
            default => null,
        };
    }

    public static function number(mixed $value, ?int $decimals = null, bool $grouping = true, ?string $locale = null): ?string
    {
        $number = self::numeric($value);

        if ($number === null) {
            return null;
        }

        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::DECIMAL);

        if ($decimals !== null) {
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        }

        $formatter->setAttribute(NumberFormatter::GROUPING_USED, $grouping ? 1 : 0);
        $text = $formatter->format($number);

        return $text === false ? null : $text;
    }

    /**
     * A decimal amount string ("-1234.50"); minor units (cents) are converted with bcmath, never floats.
     */
    public static function amount(mixed $value, bool $minorUnits = false, int $digits = 2): ?string
    {
        $text = match (true) {
            is_int($value) => (string) $value,
            is_float($value) && is_finite($value) => sprintf('%.10F', $value),
            is_string($value) && preg_match('/^\s*-?\d{1,30}(?:\.\d{1,12})?\s*$/', $value) === 1 => trim($value),
            default => null,
        };

        if ($text === null || ! $minorUnits) {
            return $text;
        }

        return preg_match('/^-?\d+$/', $text) === 1 ? bcdiv($text, bcpow('10', (string) $digits), $digits) : null;
    }

    /**
     * The currency's minor-unit digits from ICU data (0 for XAF, 2 for EUR).
     */
    public static function currencyDigits(string $currency): int
    {
        return (int) (new NumberFormatter('en@currency='.$currency, NumberFormatter::CURRENCY))->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    public static function money(?string $amount, string $currency, ?int $decimals = null, ?string $locale = null): ?string
    {
        if ($amount === null) {
            return null;
        }

        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);

        if ($decimals !== null) {
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        }

        // NumberFormatter takes a float; amounts up to 15 significant digits display exactly.
        $text = $formatter->formatCurrency((float) $amount, $currency);

        return $text === false ? null : $text;
    }

    /**
     * An immutable instant: stored values are read in $storageZone; with $displayZone they are converted (DateTime), without it they stay on their calendar day (Date).
     */
    public static function moment(mixed $value, ?string $displayZone = null): ?CarbonImmutable
    {
        $storage = config('app.timezone', 'UTC');
        $storage = is_string($storage) ? $storage : 'UTC';

        try {
            $date = match (true) {
                $value instanceof DateTimeInterface => CarbonImmutable::instance($value),
                is_string($value) && $value !== '' => CarbonImmutable::parse($value, $storage),
                is_int($value) => CarbonImmutable::createFromTimestamp($value, $storage),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        return $date === null || $displayZone === null ? $date : $date->setTimezone($displayZone);
    }

    /**
     * Locale-aware text for an instant: a PHP format, relative time, or an ISO preset ("ll", "lll").
     */
    public static function date(CarbonImmutable $date, ?string $format = null, bool $relative = false, string $iso = 'll'): string
    {
        $localized = $date->locale(app()->getLocale());
        $localized = $localized instanceof CarbonImmutable ? $localized : $date;

        if ($relative) {
            return $localized->diffForHumans();
        }

        return $format === null ? $localized->isoFormat($iso) : $date->format($format);
    }
}
