<?php

declare(strict_types=1);

namespace Gcego\Widgets\Progress;

use Carbon\CarbonImmutable;
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
 * Progress towards a target, with what remains and an optional deadline:
 *
 *     new Goal('Enrolment target', new Aggregate(Student::class), new Property(Goal::Target, 1500), new Property(Goal::Deadline, '2026-09-30'))
 *     new Goal('Absences this term', new Aggregate(Absence::class), new Property(Goal::Target, 200), new Property(Goal::Direction, 'at_most'))
 *
 * Direction "at_least" (default): reaching or passing the target is
 * achieved. "at_most": the target is a limit, and passing it is exceeded.
 */
final class Goal extends Widget
{
    public const string Target = 'target';

    public const string Deadline = 'deadline';

    public const string Direction = 'direction';

    public const string Format = KpiFormat::Format;

    public const string Decimals = KpiFormat::Decimals;

    public const string Currency = KpiFormat::Currency;

    public function view(): string
    {
        return 'gcego-widgets::progress.goal';
    }

    public function target(): int|float|null
    {
        $target = $this->get(self::Target);

        return is_int($target) || is_float($target) ? $target : null;
    }

    public function deadline(): ?CarbonImmutable
    {
        $deadline = $this->get(self::Deadline);

        return is_string($deadline) ? CarbonImmutable::createFromFormat('!Y-m-d', $deadline) ?: null : null;
    }

    /**
     * @return array{percent: float|null, clamped: float|null, remaining: int|float|null, status: 'achieved'|'on_track'|'behind'|'exceeded'|'within'|'unknown'}
     */
    public function evaluate(int|float|null $current, int|float|null $target, ?CarbonImmutable $now = null): array
    {
        $atMost = $this->get(self::Direction) === 'at_most';
        ['percent' => $percent, 'clamped' => $clamped] = Progress::percent($current, 0, $target);

        if ($current === null || $target === null || $percent === null) {
            return ['percent' => null, 'clamped' => null, 'remaining' => null, 'status' => 'unknown'];
        }

        if ($atMost) {
            return ['percent' => $percent, 'clamped' => $clamped, 'remaining' => max(0, $target - $current), 'status' => $current > $target ? 'exceeded' : 'within'];
        }

        $status = 'on_track';

        if ($current >= $target) {
            $status = 'achieved';
        } elseif (($deadline = $this->deadline()) !== null) {
            $now ??= CarbonImmutable::now();
            $status = $now->startOfDay() > $deadline ? 'behind' : 'on_track';
        }

        return ['percent' => $percent, 'clamped' => $clamped, 'remaining' => max(0, $target - $current), 'status' => $status];
    }

    public function viewData(WidgetDataResult $result, WidgetsConfig $config, WidgetRenderFrame $frame): array
    {
        $current = Formats::numeric($result->value);
        $target = $result->maximum ?? $this->target();
        $evaluation = $this->evaluate($current, $target);

        return [
            ...$evaluation,
            'value' => $this->formatValue($current, $config->currency),
            'target' => $this->formatValue($target, $config->currency),
            'remainingText' => $this->formatValue($evaluation['remaining'], $config->currency),
            'deadline' => $this->deadline(),
            'now' => $current,
            'max' => $target,
            'empty' => $current === null,
        ];
    }

    public function formatValue(int|float|null $value, string $defaultCurrency): ?string
    {
        $decimals = $this->get(self::Decimals);

        return KpiFormat::format($value, $this->stringProperty(self::Format) ?? 'number', is_int($decimals) ? $decimals : null, $this->stringProperty(self::Currency) ?? $defaultCurrency);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Target, [Type::int(), Type::float()]),
            new PropertyDefinition(self::Deadline, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && CarbonImmutable::createFromFormat('!Y-m-d', $value) !== null, ruleDescription: 'a date (YYYY-MM-DD)'),
            new PropertyDefinition(self::Direction, Type::string(), default: 'at_least', allowedValues: ['at_least', 'at_most']),
            ...KpiFormat::properties(),
        );
    }
}
