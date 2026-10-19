<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * Multi-line plain text: new Textarea('description', 'Description', new Property(Textarea::Rows, 6), new Property(Textarea::MaxLength, 2000), new Property(Textarea::Counter, true))
 */
class Textarea extends Field
{
    public const string Rows = 'rows';

    public const string MinLength = 'min_length';

    public const string MaxLength = 'max_length';

    /** Show "n / max" characters (needs MaxLength). */
    public const string Counter = 'counter';

    public function view(): string
    {
        return 'gcego-forms::fields.textarea';
    }

    public function typeRules(): array
    {
        $rules = ['string'];
        $min = $this->intProperty(self::MinLength);
        $max = $this->intProperty(self::MaxLength);

        if ($min !== null) {
            $rules[] = 'min:'.$min;
        }

        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Rows, Type::int(), default: 4, rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 50, ruleDescription: 'between 1 and 50'),
            new PropertyDefinition(self::MinLength, Type::int()),
            new PropertyDefinition(self::MaxLength, Type::int()),
            new PropertyDefinition(self::Counter, Type::bool(), default: false),
        );
    }
}
