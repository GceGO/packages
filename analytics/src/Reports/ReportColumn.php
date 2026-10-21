<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Analytics\Support\Format;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A report column: a dimension to group by, or an aggregate of a field.
 *
 *     new ReportColumn('subject_id', 'Subject')                                            // the dimension of the same name
 *     new ReportColumn('attempts', 'Attempts', aggregate: 'count')
 *     new ReportColumn('average_score', 'Average score', aggregate: 'avg', field: 'score', format: 'number', precision: 1)
 *     new ReportColumn('subject_id', 'Subject', drillDown: 'subject-performance')          // links to a report filtered by this key
 */
final class ReportColumn extends AnalyticsNode
{
    public const string Dimension = 'dimension';

    public const string Aggregate = 'aggregate';

    public const string Field = 'field';

    public const string Format = 'format';

    public const string Precision = 'precision';

    public const string Currency = 'currency';

    public const string Unit = 'unit';

    public const string Sortable = 'sortable';

    /** A report to open, filtered by this row's dimension key (re-authorized there). */
    public const string DrillDown = 'drill_down';

    public function isMeasure(): bool
    {
        return $this->aggregate() !== null;
    }

    public function dimension(): string
    {
        return $this->text(self::Dimension) ?? $this->nodeName();
    }

    public function aggregate(): ?Aggregate
    {
        return Aggregate::tryFrom($this->text(self::Aggregate) ?? '');
    }

    public function field(): ?string
    {
        return $this->text(self::Field);
    }

    public function format(): Format
    {
        return Format::tryFrom($this->text(self::Format) ?? '') ?? ($this->isMeasure() ? Format::Number : Format::Text);
    }

    public function precision(): ?int
    {
        return $this->integer(self::Precision) ?? (in_array($this->aggregate(), [Aggregate::Count, Aggregate::CountDistinct], true) ? 0 : null);
    }

    public function currency(): ?string
    {
        return $this->text(self::Currency);
    }

    public function unit(): ?string
    {
        return $this->text(self::Unit);
    }

    public function isSortable(): bool
    {
        return $this->get(self::Sortable) !== false;
    }

    public function drillDown(): ?string
    {
        return $this->text(self::DrillDown);
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'dimension' => self::Dimension, 'aggregate' => self::Aggregate, 'field' => self::Field, 'format' => self::Format, 'precision' => self::Precision, 'currency' => self::Currency, 'unit' => self::Unit, 'sortable' => self::Sortable, 'drillDown' => self::DrillDown];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.report-column')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validColumn(...), ruleDescription: 'a column key (letters, digits, underscores)'),
                new PropertyDefinition(self::Dimension, Type::string(), rule: self::validColumn(...), ruleDescription: 'a dimension of the dataset'),
                new PropertyDefinition(self::Aggregate, Type::string(), allowedValues: Aggregate::values()),
                new PropertyDefinition(self::Field, Type::string(), rule: self::validColumn(...), ruleDescription: 'a field of the dataset'),
                new PropertyDefinition(self::Format, Type::string(), allowedValues: Format::values()),
                new PropertyDefinition(self::Precision, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0 && $value <= 6, ruleDescription: 'between 0 and 6'),
                new PropertyDefinition(self::Currency, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Z]{3}$/', $value) === 1, ruleDescription: 'an ISO 4217 code'),
                new PropertyDefinition(self::Unit, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 20, ruleDescription: 'at most 20 characters'),
                new PropertyDefinition(self::Sortable, Type::bool(), default: true),
                new PropertyDefinition(self::DrillDown, Type::string(), rule: self::validName(...), ruleDescription: 'a report name'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
