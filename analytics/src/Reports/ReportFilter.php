<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A filter people may set on a report or dashboard: a dimension (values
 * from its options or the keys the viewer can see) or a filterable field.
 *
 *     new ReportFilter('level', 'Level')
 *     new ReportFilter('subject_id', 'Subjects', multiple: true)
 *     new ReportFilter('score', 'Minimum score', operator: 'gte')
 *
 * Values are validated against the dataset schema on every request; a
 * filter can only narrow what the viewer may already see.
 */
final class ReportFilter extends AnalyticsNode
{
    public const string Multiple = 'multiple';

    /** eq, gte or lte (fields); dimensions use eq or in (Multiple). */
    public const string Operator = 'operator';

    public const string Default = 'default';

    public const string Required = 'required';

    public function target(): string
    {
        return $this->nodeName();
    }

    public function isMultiple(): bool
    {
        return $this->get(self::Multiple) === true;
    }

    public function operator(): string
    {
        return $this->isMultiple() ? 'in' : ($this->text(self::Operator) ?? 'eq');
    }

    public function default(): mixed
    {
        return $this->get(self::Default);
    }

    public function isRequired(): bool
    {
        return $this->get(self::Required) === true;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'multiple' => self::Multiple, 'operator' => self::Operator, 'default' => self::Default, 'required' => self::Required];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.filter')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validColumn(...), ruleDescription: 'a dimension or field of the dataset'),
                new PropertyDefinition(self::Multiple, Type::bool(), default: false),
                new PropertyDefinition(self::Operator, Type::string(), default: 'eq', allowedValues: ['eq', 'gte', 'lte']),
                new PropertyDefinition(self::Default, Type::mixed(), rule: static fn (mixed $value): bool => is_scalar($value) || (is_array($value) && count($value) <= 100 && array_filter($value, static fn (mixed $entry): bool => ! is_scalar($entry)) === []), ruleDescription: 'a value or a list of values'),
                new PropertyDefinition(self::Required, Type::bool(), default: false),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
