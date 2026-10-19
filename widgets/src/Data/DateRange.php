<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Carbon\CarbonImmutable;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Widgets\Exceptions\InvalidWidgetException;
use Gcego\Widgets\Exceptions\InvalidWidgetStateException;

/**
 * A date-range selector for a widget (and the period its Aggregates use):
 *
 *     new DateRange()                                                         // last 30 days, all presets, custom ranges
 *     new DateRange(default: 'this_month', presets: ['this_month', 'last_month', 'this_year'], custom: false)
 *
 * Presets: today, yesterday, last_7_days, last_30_days, this_month,
 * last_month, this_year, custom. Day boundaries are taken in the display
 * zone (gcego-widgets.dates.timezone, else app.timezone) and converted to
 * the storage zone; ranges are inclusive and at most dates.max_range_days long.
 */
final readonly class DateRange implements ConfigurationNode
{
    public const array Presets = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year'];

    /**
     * @param  list<string>  $presets
     */
    public function __construct(
        public string $default = 'last_30_days',
        public array $presets = self::Presets,
        public bool $custom = true,
    ) {
        if ($presets === [] || array_diff($presets, self::Presets) !== []) {
            throw InvalidWidgetException::invalid('DateRange', 'presets must be among '.implode(', ', self::Presets).'.');
        }

        if (! in_array($default, $presets, true)) {
            throw InvalidWidgetException::invalid('DateRange', 'the default must be one of its presets.');
        }
    }

    /**
     * Untrusted state (preset, and from/to for custom ranges) to a Period.
     *
     * @throws InvalidWidgetStateException
     */
    public function resolve(?string $preset, ?string $from, ?string $to, string $displayZone, int $maxDays, ?CarbonImmutable $now = null): Period
    {
        $preset ??= $this->default;
        $now = ($now ?? CarbonImmutable::now())->setTimezone($displayZone);
        $storage = self::storageZone();

        if ($preset === 'custom') {
            if (! $this->custom) {
                throw InvalidWidgetStateException::invalid('range', 'does not allow custom ranges.');
            }

            $start = self::day($from, 'from', $displayZone);
            $end = self::day($to, 'to', $displayZone)->endOfDay();

            if ($start > $end) {
                throw InvalidWidgetStateException::invalid('range', 'starts after it ends.');
            }

            $period = new Period('custom', $start->setTimezone($storage), $end->setTimezone($storage), $displayZone);

            if ($period->days() > $maxDays) {
                throw InvalidWidgetStateException::invalid('range', sprintf('cannot exceed %d days.', $maxDays));
            }

            return $period;
        }

        if (! in_array($preset, $this->presets, true)) {
            throw InvalidWidgetStateException::invalid('range', 'is not an offered range.');
        }

        [$start, $end] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };

        return new Period($preset, $start->setTimezone($storage), $end->setTimezone($storage), $displayZone);
    }

    /**
     * @return array{default: string, presets: list<string>, custom: bool}
     */
    public function normalize(): array
    {
        return ['default' => $this->default, 'presets' => $this->presets, 'custom' => $this->custom];
    }

    private static function day(?string $value, string $field, string $zone): CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw InvalidWidgetStateException::invalid($field, 'must be a date (YYYY-MM-DD).');
        }

        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            throw InvalidWidgetStateException::invalid($field, 'must be a valid date.');
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return $date instanceof CarbonImmutable ? $date : throw InvalidWidgetStateException::invalid($field, 'must be a valid date.');
    }

    private static function storageZone(): string
    {
        $zone = config('app.timezone', 'UTC');

        return is_string($zone) ? $zone : 'UTC';
    }
}
