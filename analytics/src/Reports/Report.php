<?php

declare(strict_types=1);

namespace Gcego\Analytics\Reports;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A report: grouped rows of a dataset.
 *
 *     return new Report('student-performance', 'Student Performance Report',
 *         new Ability('reports.academic'),
 *         new ReportColumn('subject_id', 'Subject'),
 *         new ReportColumn('attempts', 'Attempts', aggregate: 'count'),
 *         new ReportColumn('average_score', 'Average score', aggregate: 'avg', field: 'score', precision: 1),
 *         new ReportFilter('level', 'Level'),
 *         dataset: 'assessment-results',
 *         defaultRange: 'last_30_days',
 *         sort: 'average_score',
 *         export: true,
 *     );
 *
 * Rows are grouped by the dimension columns, measured by the aggregate
 * columns, computed in the database after every scope, paginated on the
 * server and bounded. Exporting and scheduling are off unless enabled,
 * and need the report's access plus ExportAbility when set. Registry kind "report".
 */
final class Report extends AnalyticsNode
{
    public const string Title = 'title';

    public const string Dataset = 'dataset';

    public const string DateColumn = 'date_column';

    /** A DateRangePreset value, or none for reports without a period. */
    public const string DefaultRange = 'default_range';

    public const string PerPage = 'per_page';

    public const string Sort = 'sort';

    /** asc or desc. */
    public const string Direction = 'direction';

    public const string Export = 'export';

    /** csv, xlsx (openspout). */
    public const string Formats = 'formats';

    public const string Schedulable = 'schedulable';

    /** An additional Gate ability required to export or schedule. */
    public const string ExportAbility = 'export_ability';

    public function title(): string
    {
        return $this->text(self::Title) ?? $this->label();
    }

    public function datasetName(): string
    {
        return $this->text(self::Dataset) ?? '';
    }

    public function dateColumn(): ?string
    {
        return $this->text(self::DateColumn);
    }

    public function defaultRange(): ?DateRangePreset
    {
        return DateRangePreset::tryFrom($this->text(self::DefaultRange) ?? '');
    }

    public function perPage(): int
    {
        return $this->integer(self::PerPage) ?? 25;
    }

    public function sort(): ?string
    {
        return $this->text(self::Sort);
    }

    public function direction(): string
    {
        return $this->text(self::Direction) ?? 'desc';
    }

    public function exportable(): bool
    {
        return $this->get(self::Export) === true;
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return $this->strings(self::Formats) ?: ['csv'];
    }

    public function schedulable(): bool
    {
        return $this->get(self::Schedulable) === true;
    }

    public function exportAbility(): ?string
    {
        return $this->text(self::ExportAbility);
    }

    /**
     * @return list<ReportColumn>
     */
    public function columns(): array
    {
        return $this->childrenOf(ReportColumn::class);
    }

    public function column(string $name): ?ReportColumn
    {
        foreach ($this->columns() as $column) {
            if ($column->nodeName() === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return list<ReportFilter>
     */
    public function filters(): array
    {
        return $this->childrenOf(ReportFilter::class);
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $columns = $this->columns();

        if ($columns === []) {
            $problems[] = 'a report needs at least one ReportColumn.';
        }

        if (array_filter($columns, static fn (ReportColumn $column): bool => $column->isMeasure()) === []) {
            $problems[] = 'a report needs at least one aggregate column.';
        }

        foreach ([array_map(static fn (ReportColumn $column): string => $column->nodeName(), $columns), array_map(static fn (ReportFilter $filter): string => $filter->nodeName(), $this->filters())] as $names) {
            foreach (array_count_values($names) as $name => $count) {
                if ($count > 1) {
                    $problems[] = sprintf('"%s" is declared more than once.', $name);
                }
            }
        }

        foreach ($columns as $column) {
            $aggregate = $column->aggregate();

            if ($aggregate !== null && $aggregate->needsField() && $column->field() === null) {
                $problems[] = sprintf('the column "%s" needs a Field for %s.', $column->nodeName(), $aggregate->value);
            }
        }

        if (($sort = $this->sort()) !== null && $this->column($sort) === null) {
            $problems[] = sprintf('the sort column "%s" is not a column of the report.', $sort);
        }

        if (($this->schedulable() || $this->exportable()) && array_diff($this->formats(), ['csv', 'xlsx']) !== []) {
            $problems[] = 'export formats are csv and xlsx.';
        }

        if ($this->schedulable() && ! $this->exportable()) {
            $problems[] = 'a schedulable report must also be exportable (deliveries are exports).';
        }

        return $problems;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'dataset' => self::Dataset, 'dateColumn' => self::DateColumn, 'defaultRange' => self::DefaultRange, 'perPage' => self::PerPage, 'sort' => self::Sort, 'direction' => self::Direction, 'export' => self::Export, 'formats' => self::Formats, 'schedulable' => self::Schedulable, 'exportAbility' => self::ExportAbility];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.report')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "student-performance"'),
                new PropertyDefinition(self::Title, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 150, ruleDescription: 'at most 150 characters'),
                new PropertyDefinition(self::Dataset, Type::string(), rule: self::validName(...), ruleDescription: 'a dataset name'),
                new PropertyDefinition(self::DateColumn, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the dataset'),
                new PropertyDefinition(self::DefaultRange, Type::string(), allowedValues: array_values(array_diff(DateRangePreset::values(), ['custom']))),
                new PropertyDefinition(self::PerPage, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 200, ruleDescription: 'between 1 and 200'),
                new PropertyDefinition(self::Sort, Type::string(), rule: self::validColumn(...), ruleDescription: 'a column of the report'),
                new PropertyDefinition(self::Direction, Type::string(), default: 'desc', allowedValues: ['asc', 'desc']),
                new PropertyDefinition(self::Export, Type::bool(), default: false),
                new PropertyDefinition(self::Formats, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [], ruleDescription: 'formats'),
                new PropertyDefinition(self::Schedulable, Type::bool(), default: false),
                new PropertyDefinition(self::ExportAbility, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $value) === 1, ruleDescription: 'a Gate ability'),
            )
            ->withAllowedChildren([ReportColumn::class, ReportFilter::class])
            ->withConstraint(static fn (ComponentInterface $report): array => $report instanceof self ? $report->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Title];
    }
}
