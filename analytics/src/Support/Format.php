<?php

declare(strict_types=1);

namespace Gcego\Analytics\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Display formats of values (escaped by Blade where shown).
 */
enum Format: string
{
    case Text = 'text';
    case Number = 'number';
    case Percentage = 'percentage';
    case Currency = 'currency';
    case Date = 'date';
    case DateTime = 'datetime';
    case Status = 'status';
    case Duration = 'duration';

    public function format(mixed $value, ?int $precision = null, ?string $currency = null, ?string $unit = null, ?string $timezone = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $locale = str_replace('_', '-', app()->getLocale());

        $formatted = match ($this) {
            self::Number => self::number($value, $precision ?? (is_float($value) ? 2 : 0), $locale),
            self::Percentage => self::number($value, $precision ?? 1, $locale).' %',
            self::Currency => self::currency($value, $currency ?? 'XAF', $precision, $locale),
            self::Date, self::DateTime => self::date($value, $this === self::DateTime, $timezone),
            self::Duration => self::duration($value),
            self::Text, self::Status => is_scalar($value) ? (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value) : '',
        };

        return $unit === null || $unit === '' || $this === self::Percentage || $this === self::Currency ? $formatted : $formatted.' '.$unit;
    }

    private static function number(mixed $value, int $precision, string $locale): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);
            $result = $formatter->format($number);

            if (is_string($result)) {
                return $result;
            }
        }

        return number_format($number, $precision);
    }

    private static function currency(mixed $value, string $currency, ?int $precision, string $locale): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

            if ($precision !== null) {
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);
            }

            $result = $formatter->formatCurrency($number, $currency);

            if (is_string($result)) {
                return $result;
            }
        }

        return number_format($number, $precision ?? 2).' '.$currency;
    }

    private static function date(mixed $value, bool $time, ?string $timezone): string
    {
        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse(is_scalar($value) ? (string) $value : '');
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : '';
        }

        if ($timezone !== null) {
            $date = $date->setTimezone($timezone);
        }

        return $time ? $date->isoFormat('lll') : $date->isoFormat('ll');
    }

    private static function duration(mixed $value): string
    {
        $seconds = is_numeric($value) ? (int) round((float) $value) : 0;

        return $seconds >= 86400 ? sprintf('%dd %dh', intdiv($seconds, 86400), intdiv($seconds % 86400, 3600))
            : ($seconds >= 3600 ? sprintf('%dh %dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)) : sprintf('%dm %ds', intdiv($seconds, 60), $seconds % 60));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
