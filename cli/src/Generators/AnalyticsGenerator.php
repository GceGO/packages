<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Cli\Exceptions\GenerationException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\PlannedFile;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego/analytics definitions, in app/GCEGo/Analytics/{Datasets,Metrics,Reports}:
 *
 *  - gcego:make-dataset StudentPerformance --model=  (fields from the model, sensitive ones left out)
 *  - gcego:make-metric ActiveStudents --dataset= | --model=  [--aggregate=count] [--field=]
 *  - gcego:make-report AcademicPerformance --dataset= [--export]
 *
 * Every definition carries a requirement: the model's "viewAny" ability, or
 * a Gate ability ("<name>.view") the application must define.
 */
final class AnalyticsGenerator extends AbstractGenerator
{
    private const array Aggregates = ['count', 'sum', 'avg', 'min', 'max', 'count_distinct'];

    private string $kind = 'metric';

    public function forKind(string $kind): self
    {
        if (! in_array($kind, ['dataset', 'metric', 'report'], true)) {
            throw GenerationException::invalid(sprintf('Unknown analytics kind "%s".', $kind));
        }

        $generator = clone $this;
        $generator->kind = $kind;

        return $generator;
    }

    public function id(): string
    {
        return $this->kind;
    }

    public function description(): string
    {
        return match ($this->kind) {
            'dataset' => 'An analytics dataset (gcego/analytics): queryable fields of a model',
            'metric' => 'An analytics metric (gcego/analytics)',
            default => 'An analytics report (gcego/analytics) over a dataset',
        };
    }

    public function requires(): array
    {
        return ['gcego/analytics'];
    }

    public function options(): array
    {
        return match ($this->kind) {
            'dataset' => ['model' => 'Eloquent model (required)', 'ability' => 'Ability to query it (default: viewAny on the model)'],
            'metric' => ['dataset' => 'Dataset it measures', 'model' => 'Or: an Eloquent model', 'aggregate' => implode(', ', self::Aggregates), 'field' => 'Field (all but count)', 'ability' => 'Ability (model metrics)'],
            default => ['dataset' => 'Dataset (required)', 'export' => 'Allow CSV/XLSX export (needs <name>.export)', 'ability' => 'Ability to run it (default <name>.view)', 'title' => 'Title'],
        };
    }

    protected function files(GenerationContext $context): array
    {
        $root = $this->kit->paths->directory('analytics');

        return match ($this->kind) {
            'dataset' => $this->dataset($context, $root.'/Datasets'),
            'metric' => $this->metric($context, $root.'/Metrics'),
            default => $this->report($context, $root.'/Reports'),
        };
    }

    protected function test(GenerationContext $context): array
    {
        return ['analytics', $this->kind.':'.($this->kind === 'metric' ? $this->kit->names->snake($context->name) : $this->kit->names->slug($context->name))];
    }

    /**
     * @return list<PlannedFile>
     */
    private function dataset(GenerationContext $context, string $directory): array
    {
        $name = $this->kit->names->slug($context->name);
        $this->assertNew('dataset', $name, $directory);
        $model = $this->model($context, true, 'the Eloquent model the dataset reads, e.g. --model="App\\Models\\AssessmentResult".');
        assert($model !== null);
        $inspection = $this->kit->models->inspect($model);
        array_push($this->warnings, ...$inspection['warnings']);
        $requirement = $this->requirement($context, 'viewAny', $model);
        $imports = ['Gcego\\Analytics\\Datasets\\Dataset', 'Gcego\\Analytics\\Datasets\\Field', $requirement['import'], $model];
        $fields = '';

        foreach ($inspection['fields'] as $field) {
            if ($field->type === 'enum' && $field->enum !== null) {
                $imports[] = 'Gcego\\Analytics\\Dimensions\\Dimension';
                $cases = [];

                foreach (is_subclass_of($field->enum, \BackedEnum::class) ? $field->enum::cases() : [] as $case) {
                    $cases[] = sprintf("'%s' => '%s'", StubValue::string((string) $case->value)->rendered, StubValue::string($case->name)->rendered);
                }

                $options = implode(', ', $cases);
                $fields .= sprintf("\n    new Dimension('%s', '%s', options: [%s]),", $field->name, $this->kit->fields->label($field->name), $options);

                continue;
            }

            $type = match ($field->type) {
                'boolean', 'date', 'datetime', 'integer' => $field->type,
                'decimal' => 'number',
                default => 'string',
            };
            $fields .= sprintf("\n    new Field('%s', '%s', type: '%s'),", $field->name, $this->kit->fields->label($field->name), $type);
        }

        $instance = new $model;
        $dateColumn = '';

        // Timestamps are left out of the inspected fields; the creation time is the dataset's date column.
        if ($instance->usesTimestamps()) {
            $created = (string) $instance->getCreatedAtColumn();
            $fields .= sprintf("\n    new Field('%s', 'Created', type: 'datetime'),", $created);
            $dateColumn = sprintf("\n\n    dateColumn: '%s',", $created);
        }

        if ($inspection['excluded'] !== []) {
            $this->next(sprintf('Left out as sensitive: %s.', implode(', ', $inspection['excluded'])));
        }

        return [$this->file($context, $directory.'/'.$name.'.php', 'dataset', [
            'imports' => $this->imports($imports),
            'name' => StubValue::string($name),
            'model_class' => StubValue::identifier(class_basename($model)),
            'requirement' => StubValue::code($requirement['code']),
            'fields' => StubValue::code($fields),
            'date_column' => StubValue::code($dateColumn),
        ], 'Dataset "'.$name.'"')];
    }

    /**
     * @return list<PlannedFile>
     */
    private function metric(GenerationContext $context, string $directory): array
    {
        $name = $this->kit->names->snake($context->name);
        $this->assertNew('metric', $name, $directory);
        $aggregate = $context->string('aggregate') ?? 'count';
        $field = $context->string('field');
        $dataset = $context->string('dataset');

        if (! in_array($aggregate, self::Aggregates, true)) {
            throw GenerationException::invalid(sprintf('--aggregate must be one of %s.', implode(', ', self::Aggregates)));
        }

        if ($aggregate !== 'count' && $field === null) {
            throw GenerationException::invalid(sprintf('--field is required for the "%s" aggregate.', $aggregate));
        }

        if ($field !== null && preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $field) !== 1) {
            throw GenerationException::invalid(sprintf('--field "%s" is not a valid field name.', $field));
        }

        $arguments = [];
        $imports = ['Gcego\\Analytics\\Metrics\\Metric'];

        if ($dataset !== null) {
            $this->assertExists('dataset', $this->kit->names->identifier($dataset, 'dataset'));
            $arguments[] = sprintf("dataset: '%s'", $dataset);
        } else {
            $model = $this->model($context, true, 'or --dataset: what the metric measures.');
            assert($model !== null);
            $requirement = $this->requirement($context, 'viewAny', $model);
            $imports = [...$imports, $requirement['import'], $model];
            array_unshift($arguments, $requirement['code']);
            $arguments[] = sprintf('model: %s::class', class_basename($model));
        }

        $arguments = [...array_slice($arguments, 0, $dataset === null ? 1 : 0), sprintf("name: '%s'", $name), sprintf("label: '%s'", StubValue::string($this->kit->names->title($context->name))->rendered), ...array_slice($arguments, $dataset === null ? 1 : 0), sprintf("aggregate: '%s'", $aggregate)];

        if ($field !== null) {
            $arguments[] = sprintf("field: '%s'", $field);
        }

        return [$this->file($context, $directory.'/'.$name.'.php', 'metric', [
            'imports' => $this->imports($imports),
            'name' => StubValue::string($name),
            'arguments' => StubValue::code(implode(', ', $arguments)),
        ], 'Metric "'.$name.'"')];
    }

    /**
     * @return list<PlannedFile>
     */
    private function report(GenerationContext $context, string $directory): array
    {
        $name = $this->kit->names->slug($context->name);
        $this->assertNew('report', $name, $directory);
        $dataset = $this->kit->names->identifier($context->required('dataset', 'the dataset the report reads (php artisan gcego:make-dataset first).'), 'dataset');
        $definition = $this->assertExists('dataset', $dataset);
        $requirement = $this->requirement($context, $name.'.view');
        $columns = '';

        foreach ($definition instanceof Dataset ? $definition->children() : [] as $child) {
            if ($child instanceof Dimension) {
                $columns .= sprintf("\n    new ReportColumn('%s', '%s'),", $child->name(), StubValue::string($child->label())->rendered);

                break;
            }
        }

        $columns .= "\n    new ReportColumn('records', 'Records', aggregate: 'count'),";
        $export = '';

        if ($context->flag('export')) {
            $export = sprintf("\n    export: true,\n    exportAbility: '%s.export',", $name);
            $this->next(sprintf("Define who may export: Gate::define('%s.export', ...).", $name));
        }

        if ($context->string('ability') === null) {
            $this->next(sprintf("Define who may run it: Gate::define('%s.view', ...) — nobody can until you do.", $name));
        }

        return [$this->file($context, $directory.'/'.$name.'.php', 'report', [
            'imports' => $this->imports(['Gcego\\Analytics\\Reports\\Report', 'Gcego\\Analytics\\Reports\\ReportColumn', $requirement['import']]),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $this->kit->names->title($context->name)),
            'requirement' => StubValue::code($requirement['code']),
            'columns' => StubValue::code($columns),
            'dataset' => StubValue::string($dataset),
            'export' => StubValue::code($export),
        ], 'Report "'.$name.'"')];
    }

    private function assertNew(string $kind, string $name, string $directory): void
    {
        $this->assertAvailable($kind.':'.$name, $directory.'/'.$name.'.php');
    }

    private function assertExists(string $kind, string $name): ?object
    {
        if (! $this->kit->catalog->has($kind.':'.$name)) {
            throw GenerationException::missing(sprintf('The %s "%s" does not exist (php artisan gcego:make-%s %s).', $kind, $name, $kind, $name));
        }

        return $this->kit->catalog->definition($kind.':'.$name);
    }
}
