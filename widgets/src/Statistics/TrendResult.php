<?php

declare(strict_types=1);

namespace Gcego\Widgets\Statistics;

use Gcego\Widgets\Configuration\Trend;

/**
 * A computed comparison: direction, absolute and relative change, and
 * whether that change is good, bad or neutral for this metric.
 */
final readonly class TrendResult
{
    /**
     * @param  'up'|'down'|'flat'  $direction
     * @param  'positive'|'negative'|'neutral'  $sentiment
     */
    public function __construct(
        public string $direction,
        public int|float $difference,
        public ?float $percent,
        public string $sentiment,
    ) {}

    public static function between(int|float|null $current, int|float|null $previous, Trend $trend): ?self
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $difference = $current - $previous;
        $direction = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat');
        $percent = (float) $previous === 0.0 ? null : round($difference / abs($previous) * 100, 1);
        $sentiment = match (true) {
            $direction === 'flat', $trend->increase === 'neutral' => 'neutral',
            ($direction === 'up') === ($trend->increase === 'positive') => 'positive',
            default => 'negative',
        };

        return new self($direction, $difference, $percent, $sentiment);
    }

    public function tone(): string
    {
        return match ($this->sentiment) {
            'positive' => 'success',
            'negative' => 'danger',
            default => 'neutral',
        };
    }
}
