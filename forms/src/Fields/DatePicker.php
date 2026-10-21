<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use DateTimeInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\FormsConfig;

/**
 * A calendar date (native <input type="date">), stored as "Y-m-d".
 * Dates are never time-zone converted: the day the user picks is the day stored.
 */
class DatePicker extends Field
{
    public const string Min = 'min';

    public const string Max = 'max';

    public function view(): string
    {
        return 'gcego-forms::fields.date';
    }

    public function inputType(): string
    {
        return 'date';
    }

    public function format(): string
    {
        return 'Y-m-d';
    }

    public function typeRules(): array
    {
        $rules = ['date_format:'.$this->format()];
        $min = $this->stringProperty(self::Min);
        $max = $this->stringProperty(self::Max);

        if ($min !== null) {
            $rules[] = 'after_or_equal:'.$min;
        }

        if ($max !== null) {
            $rules[] = 'before_or_equal:'.$max;
        }

        return $rules;
    }

    public function hydrate(mixed $value, FormsConfig $config): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format($this->format()) : $value;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $valid = static fn (string $format): \Closure => static fn (mixed $value): bool => is_string($value) && ($date = \DateTimeImmutable::createFromFormat('!'.$format, $value)) !== false && $date->format($format) === $value;

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Min, Type::string(), rule: $valid(static::boundFormat()), ruleDescription: 'a '.static::boundFormat().' value'),
            new PropertyDefinition(self::Max, Type::string(), rule: $valid(static::boundFormat()), ruleDescription: 'a '.static::boundFormat().' value'),
        );
    }

    protected static function boundFormat(): string
    {
        return 'Y-m-d';
    }
}
