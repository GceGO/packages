<?php

declare(strict_types=1);

use Gcego\Widgets\Configuration\Cache;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Diagnostics\WidgetInspector;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Tests\Fixtures\Providers\StudentCountProvider;
use Gcego\Widgets\Tests\Fixtures\Widgets;
use Gcego\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('aggregates large datasets with a constant number of queries', function (): void {
    $stamp = '2026-06-15 12:00:00';

    for ($chunk = 0; $chunk < 20; $chunk++) {
        $rows = [];

        for ($j = 0; $j < 1000; $j++) {
            $i = $chunk * 1000 + $j;
            $rows[] = ['name' => 'S'.$i, 'level' => $i % 6 + 1, 'gender' => $i % 2 ? 'f' : 'm', 'enrolled_at' => sprintf('2026-%02d-%02d 08:00:00', $i % 6 + 1, $i % 28 + 1), 'institution_id' => $i % 5 === 0 ? 2 : 1, 'created_at' => $stamp, 'updated_at' => $stamp];
        }

        DB::table('students')->insert($rows);
    }

    $measure = function (string $widget, array $state = []): array {
        $started = hrtime(true);
        $queries = queriesDuring(fn () => widgetHtml($widget, $state));

        return [count($queries), round((hrtime(true) - $started) / 1e6, 1)];
    };

    [$overviewQueries, $overviewMs] = $measure('overview');
    [$chartQueries, $chartMs] = $measure('enrolment');
    [$barQueries, $barMs] = $measure('students_by_level');
    [$feedQueries, $feedMs] = $measure('recent_activity');

    $started = hrtime(true);

    for ($i = 0; $i < 10; $i++) {
        widgetHtml('enrolment', ['range' => $i % 2 ? 'this_year' : 'last_month']);
    }

    $refreshMs = round((hrtime(true) - $started) / 1e6 / 10, 1);

    fwrite(STDERR, sprintf("\n[widgets perf] 20,004 students: overview stats %d queries / %.1f ms; line chart %d / %.1f ms; stacked bar (2 datasets) %d / %.1f ms; activity feed %d / %.1f ms; chart refresh avg %.1f ms\n", $overviewQueries, $overviewMs, $chartQueries, $chartMs, $barQueries, $barMs, $feedQueries, $feedMs, $refreshMs));

    expect($overviewQueries)->toBeLessThanOrEqual(6)
        ->and($chartQueries)->toBe(1)
        ->and($barQueries)->toBe(2)
        ->and($feedQueries)->toBe(2)
        ->and(widgetHtml('total_students'))->toContain('16,004');
});

it('inspects widgets without running their data', function (): void {
    StudentCountProvider::$calls = 0;
    $queries = queriesDuring(fn () => $this->report = app(WidgetInspector::class)->inspect('live_students'));

    expect($queries)->toBe([])
        ->and(StudentCountProvider::$calls)->toBe(0)
        ->and($this->report->isHealthy())->toBeTrue()
        ->and($this->report->checks())->toContain('refresh')
        ->and($this->report->widgets[0]['source'])->toBe('provider:'.StudentCountProvider::class)
        ->and(app(WidgetInspector::class)->inspect('overview')->widgets)->toHaveCount(7);
});

it('reports broken widgets', function (): void {
    $report = app(WidgetInspector::class)->inspect(new Stat('Orphan', new Aggregate('blueprint:missing'), new Cache(scope: 'tenant')));

    expect($report->isHealthy())->toBeFalse()
        ->and($report->checks())->toContain('registry', 'authorization', 'source', 'cache')
        ->and(app(WidgetInspector::class)->inspect('nope')->checks())->toBe(['registry']);
});

it('registers services and defines widgets without queries', function (): void {
    expect(queriesDuring(function (): void {
        (new WidgetsServiceProvider(app()))->register();
        Widgets::all();
    }))->toBe([]);
});
