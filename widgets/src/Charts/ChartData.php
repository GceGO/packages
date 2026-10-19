<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * Validated chart data (see ChartDataNormalizer): labels and datasets of equal length.
 */
final readonly class ChartData
{
    /**
     * @param  list<string>  $labels
     * @param  list<ChartDataset>  $datasets
     */
    public function __construct(public array $labels, public array $datasets) {}

    public function isEmpty(): bool
    {
        foreach ($this->datasets as $dataset) {
            foreach ($dataset->data as $value) {
                if ($value !== null) {
                    return false;
                }
            }
        }

        return true;
    }
}
