<?php

declare(strict_types=1);

namespace Gcego\Widgets\Statistics;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\SafeUrl;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Support\KpiFormat;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * A KPI card: a label, a formatted value, optional description, icon, trend and link.
 *
 *     new Stat('Total Students', StudentCountProvider::class)
 *     new Stat('Total Students', new Aggregate(Student::class))
 *     new Stat('Revenue', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', compare: true), new Trend(),
 *         new Property(Stat::Format, 'currency'), new DateRange())
 *     new Stat('Failed assessments', new Aggregate(Result::class, filters: [new Filter('passed', false)], date: 'graded_at', compare: true), new Trend(increase: 'negative'))
 *
 * Formats: number, decimal, percent, currency, compact, duration, ratio.
 */
class Stat extends Widget
{
    public const string Format = KpiFormat::Format;

    public const string Decimals = KpiFormat::Decimals;

    public const string Currency = KpiFormat::Currency;

    public const string Prefix = KpiFormat::Prefix;

    public const string Suffix = KpiFormat::Suffix;

    public const string PercentScale = KpiFormat::PercentScale;

    /** default | success | warning | danger | info | neutral: the icon accent. */
    public const string Tone = 'tone';

    /** A link to more detail (http(s) or root-relative). */
    public const string Url = 'url';

    public const array Tones = ['default', 'success', 'warning', 'danger', 'info', 'neutral'];

    public function view(): string
    {
        return 'gcego-widgets::statistics.stat';
    }

    public function trend(): ?Trend
    {
        $trend = $this->configurations()->first(Trend::class);

        return $trend instanceof Trend ? $trend : null;
    }

    public function formatValue(mixed $value, string $defaultCurrency): ?string
    {
        $currency = $this->stringProperty(self::Currency) ?? $defaultCurrency;
        $percentScale = $this->get(self::PercentScale);
        $decimals = $this->get(self::Decimals);
        $text = KpiFormat::format($value, $this->stringProperty(self::Format) ?? 'number', is_int($decimals) ? $decimals : null, $currency, $percentScale === 100 ? 100 : 1);

        return $text === null ? null : ($this->stringProperty(self::Prefix) ?? '').$text.($this->stringProperty(self::Suffix) ?? '');
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        $number = Formats::numeric($result->value);
        $trend = $this->trend();
        $comparison = $trend === null ? null : TrendResult::between($number, $result->previous, $trend);

        return [
            'value' => $this->formatValue($result->value, $config->currency),
            'raw' => $number,
            'trend' => $comparison,
            'trendText' => $comparison === null || $trend === null ? null : $this->describeTrend($comparison, $trend, $config),
            'tone' => $this->stringProperty(self::Tone) ?? 'default',
            'url' => $this->url(),
            'empty' => $result->value === null,
        ];
    }

    /**
     * A sentence for screen readers and the visible caption: direction, amount and meaning.
     */
    public function describeTrend(TrendResult $comparison, Trend $trend, WidgetsConfig $config): string
    {
        $amount = match ($trend->show) {
            'absolute' => $this->formatValue(abs($comparison->difference), $config->currency),
            'both' => $this->formatValue(abs($comparison->difference), $config->currency).($comparison->percent === null ? '' : ' ('.abs($comparison->percent).'%)'),
            default => $comparison->percent === null ? $this->formatValue(abs($comparison->difference), $config->currency) : abs($comparison->percent).'%',
        };
        $period = $trend->label ?? 'gcego-widgets::messages.previous_period';

        return (string) __('gcego-widgets::messages.trend_'.$comparison->direction, ['amount' => (string) $amount, 'period' => (string) __($period)])
            .($comparison->sentiment === 'neutral' ? '' : ' '.(string) __('gcego-widgets::messages.trend_'.$comparison->sentiment));
    }

    public function url(): ?string
    {
        $url = $this->stringProperty(self::Url);

        return $url === null ? null : SafeUrl::web($url);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            ...KpiFormat::properties(),
            ...[
                new PropertyDefinition(self::Tone, Type::string(), default: 'default', allowedValues: self::Tones),
                new PropertyDefinition(self::Url, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && SafeUrl::web($value) !== null, ruleDescription: 'an http(s) or root-relative URL'),
            ],
        );
    }
}
