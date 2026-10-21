<?php

declare(strict_types=1);

namespace Gcego\Widgets\Progress;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\Support\Formats;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Support\KpiFormat;
use Gcego\Widgets\Widget;
use Gcego\Widgets\WidgetsConfig;

/**
 * A progress bar with its value and percentage:
 *
 *     new Progress(new Property(Progress::Name, 'syllabus_completion'), new Property(Progress::Label, 'Syllabus Completion'),
 *         new Property(Progress::Value, 72), new Property(Progress::Maximum, 100))
 *     new Progress('Fees collected', new DataProvider(FeesCollectedProvider::class))      // WidgetDataResult::progress($paid, $due)
 *
 * The bar is clamped to 0–100%, a zero range shows no percentage, and the
 * percentage is always written out (role="progressbar" with value text).
 */
class Progress extends Widget
{
    public const string Maximum = 'maximum';

    public const string Minimum = 'minimum';

    public const string Tone = 'tone';

    public const string Format = KpiFormat::Format;

    public const string Decimals = KpiFormat::Decimals;

    public const string Currency = KpiFormat::Currency;

    public function view(): string
    {
        return 'gcego-widgets::progress.progress';
    }

    /**
     * @return array{percent: float|null, clamped: float|null}
     */
    public static function percent(int|float|null $value, int|float|null $minimum, int|float|null $maximum): array
    {
        if ($value === null || $maximum === null) {
            return ['percent' => null, 'clamped' => null];
        }

        $range = $maximum - ($minimum ?? 0);

        if ((float) $range === 0.0) {
            return ['percent' => null, 'clamped' => null];
        }

        $percent = round(($value - ($minimum ?? 0)) / $range * 100, 1);

        return ['percent' => $percent, 'clamped' => max(0.0, min(100.0, $percent))];
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        $value = Formats::numeric($result->value);
        $maximum = $result->maximum ?? $this->maximum();
        $percent = self::percent($value, $this->minimum(), $maximum);

        return [
            ...$percent,
            'value' => $this->formatValue($value, $config->currency),
            'maximum' => $this->formatValue($maximum, $config->currency),
            'now' => $value,
            'min' => $this->minimum(),
            'max' => $maximum,
            'tone' => $this->stringProperty(self::Tone) ?? 'default',
            'empty' => $value === null,
        ];
    }

    public function maximum(): int|float|null
    {
        $maximum = $this->get(self::Maximum);

        return is_int($maximum) || is_float($maximum) ? $maximum : null;
    }

    public function minimum(): int|float
    {
        $minimum = $this->get(self::Minimum);

        return is_int($minimum) || is_float($minimum) ? $minimum : 0;
    }

    public function formatValue(int|float|null $value, string $defaultCurrency): ?string
    {
        $decimals = $this->get(self::Decimals);

        return KpiFormat::format($value, $this->stringProperty(self::Format) ?? 'number', is_int($decimals) ? $decimals : null, $this->stringProperty(self::Currency) ?? $defaultCurrency);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Maximum, [Type::int(), Type::float()], default: 100),
            new PropertyDefinition(self::Minimum, [Type::int(), Type::float()], default: 0),
            new PropertyDefinition(self::Tone, Type::string(), default: 'default', allowedValues: ['default', 'success', 'warning', 'danger', 'info', 'neutral']),
            ...KpiFormat::properties(),
        );
    }
}
