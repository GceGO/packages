<?php

declare(strict_types=1);

namespace Gcego\Widgets\Charts;

/**
 * One series of a chart: a label and one value (or null for a gap) per chart label.
 */
final readonly class ChartDataset
{
    public const array Tones = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

    /**
     * @param  list<int|float|null>  $data
     */
    public function __construct(public string $label, public array $data, public ?string $tone = null) {}
}
