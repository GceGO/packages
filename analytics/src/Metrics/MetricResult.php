<?php

declare(strict_types=1);

namespace Gcego\Analytics\Metrics;

use Carbon\CarbonImmutable;
use Gcego\Analytics\Comparisons\ComparisonResult;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Support\Format;

/**
 * A calculated metric. $value is null when there is no value (an average
 * of nothing, a zero denominator, suppressed small groups); zero is a
 * real zero. Series points and groups carry their own values and
 * suppression flags. Only aggregates and labels the viewer may see.
 */
final readonly class MetricResult
{
    /**
     * @param  list<array{key: string, label: string, value: int|float|null, formatted: string, suppressed: bool}>  $series
     * @param  list<array{key: string, label: string, value: int|float|null, formatted: string, suppressed: bool}>  $comparisonSeries
     * @param  list<array{key: string|null, label: string, value: int|float|null, formatted: string, suppressed: bool}>  $groups
     */
    public function __construct(
        public string $metric,
        public string $label,
        public int|float|null $value,
        public string $formatted,
        public Format $format,
        public ?string $unit,
        public int $precision,
        public ?DateRange $range,
        public ?ComparisonResult $comparison,
        public array $series,
        public array $comparisonSeries,
        public array $groups,
        public bool $suppressed,
        public bool $truncated,
        public CarbonImmutable $calculatedAt,
        public bool $cached,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'label' => $this->label,
            'value' => $this->value,
            'formatted' => $this->formatted,
            'format' => $this->format->value,
            'unit' => $this->unit,
            'range' => $this->range?->toArray(),
            'comparison' => $this->comparison?->toArray(),
            'series' => $this->series,
            'comparison_series' => $this->comparisonSeries,
            'groups' => $this->groups,
            'suppressed' => $this->suppressed,
            'truncated' => $this->truncated,
            'calculated_at' => $this->calculatedAt->toIso8601String(),
            'cached' => $this->cached,
        ];
    }
}
