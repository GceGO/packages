<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Gcego\Widgets\Activity\ActivityItem;
use Gcego\Widgets\Charts\ChartDataset;

/**
 * What a provider, Value or Aggregate produced for a widget:
 *
 *     WidgetDataResult::value(1240, previous: 1180)                 // a stat (and its trend)
 *     WidgetDataResult::progress(72, 100)                           // progress and goals
 *     WidgetDataResult::series('Enrolments', ['Jan' => 40, 'Feb' => 52])
 *     WidgetDataResult::chart(['Jan', 'Feb'], [new ChartDataset('Boys', [20, 25]), new ChartDataset('Girls', [20, 27])])
 *     WidgetDataResult::items([new ActivityItem('Invoice paid', at: $paidAt)], hasMore: true)
 *     WidgetDataResult::values(['students' => 1240, 'teachers' => 85])  // a shared provider for Stats
 *
 * Results are plain data; they never reach the browser except as formatted, escaped output.
 */
final readonly class WidgetDataResult
{
    /**
     * @param  list<string>  $labels
     * @param  list<ChartDataset>  $datasets
     * @param  list<ActivityItem>  $items
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public mixed $value = null,
        public int|float|null $previous = null,
        public int|float|null $maximum = null,
        public array $labels = [],
        public array $datasets = [],
        public array $items = [],
        public bool $hasMore = false,
        public array $values = [],
    ) {}

    public static function value(mixed $value, int|float|null $previous = null): self
    {
        return new self(value: $value, previous: $previous);
    }

    public static function progress(int|float|null $value, int|float|null $maximum = null): self
    {
        return new self(value: $value, maximum: $maximum);
    }

    /**
     * @param  array<array-key, int|float|null>  $points  label => value
     */
    public static function series(string $label, array $points, ?string $tone = null): self
    {
        return new self(labels: array_map(strval(...), array_keys($points)), datasets: [new ChartDataset($label, array_values($points), $tone)]);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<ChartDataset>  $datasets
     */
    public static function chart(array $labels, array $datasets): self
    {
        return new self(labels: $labels, datasets: $datasets);
    }

    /**
     * @param  list<ActivityItem>  $items
     */
    public static function items(array $items, bool $hasMore = false): self
    {
        return new self(items: $items, hasMore: $hasMore);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function values(array $values): self
    {
        return new self(values: $values);
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * The result for one child of a group (shared providers): values[name] as a value or a nested result.
     */
    public function for(string $name): ?self
    {
        if (! array_key_exists($name, $this->values)) {
            return null;
        }

        $value = $this->values[$name];

        return $value instanceof self ? $value : self::value($value);
    }
}
