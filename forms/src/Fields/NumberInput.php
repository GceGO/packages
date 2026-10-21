<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\FormsConfig;

/**
 * A number: new NumberInput('level', 'Level', new Property(NumberInput::Integer, true), new Property(NumberInput::Min, 1), new Property(NumberInput::Max, 7))
 *
 * Empty input is null, never zero. Invalid input is kept as typed so the
 * validation error is meaningful; it is never silently converted.
 */
class NumberInput extends Field
{
    public const string Integer = 'integer';

    public const string Min = 'min';

    public const string Max = 'max';

    public const string Step = 'step';

    public function view(): string
    {
        return 'gcego-forms::fields.number-input';
    }

    public function isInteger(): bool
    {
        return $this->get(self::Integer) === true;
    }

    public function typeRules(): array
    {
        $rules = [$this->isInteger() ? 'integer' : 'numeric'];
        $min = $this->get(self::Min);
        $max = $this->get(self::Max);

        if (is_int($min) || is_float($min)) {
            $rules[] = 'min:'.$min;
        }

        if (is_int($max) || is_float($max)) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function normalizeInput(mixed $value, FormsConfig $config): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (preg_match('/^-?\d{1,18}$/', $value) === 1) {
                return (int) $value;
            }

            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    public function dehydrate(mixed $value, FormsConfig $config): mixed
    {
        return $this->isInteger() && is_float($value) && floor($value) === $value ? (int) $value : $value;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $number = static fn (mixed $value): bool => is_int($value) || is_float($value);

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Integer, Type::bool(), default: false),
            new PropertyDefinition(self::Min, [Type::int(), Type::float()], rule: $number, ruleDescription: 'a number'),
            new PropertyDefinition(self::Max, [Type::int(), Type::float()], rule: $number, ruleDescription: 'a number'),
            new PropertyDefinition(self::Step, [Type::int(), Type::float(), Type::string()], rule: static fn (mixed $value): bool => $value === 'any' || (is_numeric($value) && $value > 0), ruleDescription: 'a positive number or "any"'),
        );
    }
}
