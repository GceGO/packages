<?php

declare(strict_types=1);

namespace Gcego\Analytics\Support;

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\Kpi;
use Gcego\Chroma\ChromaManager;

/**
 * Classes of analytics views: presets merged (gcego/chroma rules) with the
 * application's overrides of the Dashboard slots (wrapper, header,
 * toolbar, filters, metric, metric_value, metric_trend, chart,
 * chart_legend, report, report_table, comparison, empty, loading, error):
 *
 *     $styling->override(Dashboard::class, new Slot('metric', 'rounded-2xl p-6'));
 */
final class AnalyticsStyles
{
    /** @var array<string, string> */
    private array $merged = [];

    private ?Dashboard $prototype = null;

    public function __construct(private readonly ChromaManager $styling) {}

    /**
     * @param  list<string>  $preset
     */
    public function classes(string $slot, array $preset): string
    {
        $computed = implode(' ', $preset);
        $this->prototype ??= new Dashboard('prototype', new Kpi('prototype'));
        $own = $this->styling->classes($this->prototype, $slot);

        return $this->merged[$slot."\0".$computed] ??= $own === '' ? $computed : $this->styling->merge($computed, $own);
    }
}
