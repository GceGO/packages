<?php

declare(strict_types=1);

use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\DashboardResolver;
use Gcego\Analytics\Dashboards\DashboardState;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Analytics\Tests\Fixtures\Policies\RecordPolicy;
use Gcego\Components\Component;
use Gcego\Registry\Contracts\RegistryInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Run a README example as written (App\Models classes are the fixtures), with its variables defined.
 *
 * @param  array<string, mixed>  $variables
 */
function readmeExample(string $name, array $variables = []): mixed
{
    foreach (['App\\Models\\AssessmentResult' => AssessmentResult::class, 'App\\Models\\Subject' => Subject::class, 'App\\Models\\Student' => Subject::class] as $alias => $class) {
        if (! class_exists($alias)) {
            class_alias($class, $alias);
        }

        // Policies are looked up by the class name a Source names.
        Gate::policy($alias, RecordPolicy::class);
    }

    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = $match[1] ?? 'throw new RuntimeException("missing example");';

    return (static function () use ($code, $variables): mixed {
        extract($variables);

        return eval($code);
    })();
}

/**
 * @return list<string>
 */
function exampleProblems(Component $definition): array
{
    return array_map(static fn ($error): string => $error->format(), $definition->validate()->errors());
}

it('declares the documented dataset, metrics and styles without problems', function (): void {
    $dataset = readmeExample('dataset');
    $metric = readmeExample('metric');
    $styles = readmeExample('styles');
    $series = readmeExample('time-series');

    expect($dataset)->toBeInstanceOf(Dataset::class)->and(exampleProblems($dataset))->toBe([])->and($dataset->minimumGroupSize())->toBe(5)
        ->and($metric)->toBeInstanceOf(Metric::class)->and(exampleProblems($metric))->toBe([])
        ->and($styles)->toHaveCount(3)->and(array_unique(array_map(static fn (Metric $metric): string => $metric->nodeName().'/'.$metric->kind()->value.'/'.$metric->model(), $styles)))->toHaveCount(1)
        ->and(exampleProblems($series))->toBe([])->and($series->interval()?->value)->toBe('day');
});

it('runs the documented calculations', function (): void {
    $world = world();
    actAs($world['teacher']);

    expect(readmeExample('calculate'))->toBe([70.0, '70.0', 'up'])
        ->and(readmeExample('query', ['analytics' => analytics()]))->toBe([7, 'Mathematics', 70.0]);
});

it('runs the documented report and dashboard', function (): void {
    $world = world();
    $report = readmeExample('report');
    $dashboard = readmeExample('dashboard');
    app(RegistryInterface::class)->register($report);
    actAs($world['teacher']);

    $result = analytics()->report('student-performance')->run();
    // The fixtures already register a dashboard of that name: resolve the README one directly.
    $view = app(DashboardResolver::class)->resolve($dashboard, DashboardState::fromInput($dashboard, 'UTC'), analytics()->context());

    expect($report)->toBeInstanceOf(Report::class)->and(exampleProblems($report))->toBe([])->and($report->description())->toBe('Academic performance across subjects and assessments.')
        ->and($result->total)->toBe(3)->and($result->columns[0]['label'])->toBe('Subject')
        ->and($dashboard)->toBeInstanceOf(Dashboard::class)
        ->and(array_map(static fn (array $kpi): mixed => $kpi['result']?->value, $view->kpis))->toBe([3, 3, 70.0])
        ->and($view->charts[0]->rows[0]['label'])->toBe('Mathematics');
});
