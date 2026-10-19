<?php

declare(strict_types=1);

namespace Gcego\Analytics\Datasets;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A column a dataset exposes for measures, filters and report columns
 * (only declared fields are ever queried):
 *
 *     new Field('score', 'Score', type: 'number')
 *     new Field('time_spent', type: 'integer', aggregates: ['sum', 'avg'])
 *     new Field('email', type: 'string', sensitive: true, new Ability('students.contact'))   // field-level authorization
 *
 * Sensitive fields can be used only by people who satisfy the field's
 * requirements; they are never offered as dimensions.
 */
final class Field extends AnalyticsNode
{
    public const string Type = 'type';

    /** Aggregates allowed on the field (default: every aggregate its type supports). */
    public const string Aggregates = 'aggregates';

    public const string Sensitive = 'sensitive';

    public const string Filterable = 'filterable';

    public function column(): string
    {
        return $this->nodeName();
    }

    public function fieldType(): FieldType
    {
        return FieldType::tryFrom($this->text(self::Type) ?? '') ?? FieldType::Number;
    }

    /**
     * @return list<Aggregate>
     */
    public function aggregates(): array
    {
        $declared = $this->strings(self::Aggregates);
        $typed = $this->fieldType()->aggregates();

        return $declared === [] ? $typed : array_values(array_filter($typed, static fn (Aggregate $aggregate): bool => in_array($aggregate->value, $declared, true)));
    }

    public function allows(Aggregate $aggregate): bool
    {
        return in_array($aggregate, $this->aggregates(), true);
    }

    public function isSensitive(): bool
    {
        return $this->get(self::Sensitive) === true;
    }

    public function isFilterable(): bool
    {
        return $this->get(self::Filterable) !== false;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'type' => self::Type, 'aggregates' => self::Aggregates, 'sensitive' => self::Sensitive, 'filterable' => self::Filterable];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.field')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validColumn(...), ruleDescription: 'a column of the dataset\'s table (letters, digits, underscores)'),
                new PropertyDefinition(self::Type, Type::string(), default: 'number', allowedValues: FieldType::values()),
                new PropertyDefinition(self::Aggregates, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $aggregate): bool => ! is_string($aggregate) || ! in_array($aggregate, Aggregate::values(), true)) === [], ruleDescription: 'aggregates among '.implode(', ', Aggregate::values())),
                new PropertyDefinition(self::Sensitive, Type::bool(), default: false),
                new PropertyDefinition(self::Filterable, Type::bool(), default: true),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
