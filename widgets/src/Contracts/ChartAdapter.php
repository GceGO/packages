<?php

declare(strict_types=1);

namespace Gcego\Widgets\Contracts;

use Gcego\Widgets\Charts\Chart;
use Gcego\Widgets\Charts\ChartData;

/**
 * Turns validated chart data into a configuration a JavaScript chart library
 * can render. The result must be plain JSON data (no functions, no
 * markup): it is embedded with json_encode and read with JSON.parse.
 */
interface ChartAdapter
{
    public function id(): string;

    /**
     * The JavaScript module key the browser loads for this adapter ("chartjs").
     */
    public function module(): string;

    /**
     * @return array<string, mixed>
     */
    public function config(Chart $chart, ChartData $data): array;
}
