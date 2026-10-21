<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Tables\TablesConfig;

/**
 * A progress bar with its value as text:
 *
 *     new Progress('completion', 'Completion')                                             // 0..100
 *     new Progress('credits_done', 'Credits', new Property(Progress::Max, 180))
 *
 * Values outside Min..Max are clamped; non-numbers show the placeholder.
 * The bar has role="progressbar" with aria-valuenow/min/max and a text label.
 */
class Progress extends Column
{
    public const string Min = 'min';

    public const string Max = 'max';

    public function cellView(): string
    {
        return 'gcego-tables::columns.progress';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $percent = $this->percent($value);

        return [
            'percent' => $percent,
            'text' => $percent === null ? null : $this->text($value, $record, $config),
            'min' => $this->bound(self::Min, 0.0),
            'max' => $this->bound(self::Max, 100.0),
            'now' => $this->numeric($value),
        ];
    }

    public function percent(mixed $value): ?float
    {
        $number = $this->numeric($value);
        $min = $this->bound(self::Min, 0.0);
        $max = $this->bound(self::Max, 100.0);

        if ($number === null || $max <= $min) {
            return null;
        }

        return round(max(0.0, min(100.0, ($number - $min) / ($max - $min) * 100)), 1);
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $percent = $this->percent($value);

        return $percent === null ? null : rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';
    }

    private function numeric(mixed $value): ?float
    {
        $number = match (true) {
            is_int($value), is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => null,
        };

        return $number !== null && is_finite($number) ? $number : null;
    }

    private function bound(string $key, float $default): float
    {
        $value = $this->get($key);

        return is_int($value) || is_float($value) ? (float) $value : $default;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Min, [Type::int(), Type::float()], default: 0),
            new PropertyDefinition(self::Max, [Type::int(), Type::float()], default: 100),
        )->withConstraint(static function (ComponentInterface $column): ?string {
            $min = $column->get(self::Min);
            $max = $column->get(self::Max);

            return (is_int($min) || is_float($min)) && (is_int($max) || is_float($max)) && $max <= $min ? 'Max must be greater than Min.' : null;
        });
    }
}
