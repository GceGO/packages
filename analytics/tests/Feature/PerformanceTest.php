<?php

declare(strict_types=1);

use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\Queries\AnalyticsQuery;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Adds $count results across $subjects new subjects (institution 1, last 30 days).
 */
function seedBulk(array $world, int $count, int $subjects): void
{
    $ids = [];

    for ($i = 0; $i < $subjects; $i++) {
        $ids[] = Subject::query()->create(['name' => 'Subject '.$i, 'institution_id' => 1])->id;
    }

    foreach (array_chunk(range(1, $count), 500) as $chunk) {
        DB::table('assessment_results')->insert(array_map(static fn (int $n): array => [
            'user_id' => $world['student1']->id + ($n % 3), 'subject_id' => $ids[$n % count($ids)], 'institution_id' => 1, 'level' => $n % 2 === 0 ? 'o' : 'a',
            'kind' => 'objective', 'status' => 'completed', 'score' => $n % 101, 'time_spent' => 60, 'submitted_at' => now()->subMinutes($n % 40000)->format('Y-m-d H:i:s'),
            'created_at' => now(), 'updated_at' => now(),
        ], $chunk));
    }
}

beforeEach(function (): void {
    $this->world = world();
});

it('aggregates 20,000 rows in a few bounded queries', function (): void {
    seedBulk($this->world, 20000, 40);
    actAs($this->world['teacher']);
    $started = hrtime(true);
    $queries = queriesDuring(static function (): void {
        analytics()->metric('average_score')->calculate(range: 'last_30_days', comparison: 'previous_period');
        analytics()->metric('daily_attempts')->calculate(range: 'last_30_days');
        analytics()->metric('completion_rate')->calculate(range: 'last_30_days');
    });
    $milliseconds = (hrtime(true) - $started) / 1e6;

    $data = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'assessment_results'));

    // average (period + comparison), the daily total and its series, completion rate (its two metrics): one query each.
    expect($data)->toHaveCount(6)->and(count($queries))->toBeLessThanOrEqual(8)->and($milliseconds)->toBeLessThan(3000)
        ->and(implode("\n", $queries))->not->toContain('select * from "assessment_results"');
});

it('pages reports of many groups with a constant number of queries', function (): void {
    seedBulk($this->world, 5000, 300);
    actAs($this->world['teacher']);
    $queries = queriesDuring(static fn () => analytics()->report('subject-performance')->run(range: 'last_30_days', page: 50));

    // The page, the count of groups and the subject labels; tenant membership is verified once.
    expect(array_values(array_filter($queries, static fn (string $sql): bool => ! str_contains($sql, 'memberships'))))->toHaveCount(3)
        ->and(count($queries))->toBeLessThanOrEqual(4);
});

it('exports in chunks with bounded memory', function (): void {
    $this->reboot(['gcego-analytics.exports.chunk' => 50]);
    $world = world();
    seedBulk($world, 5000, 300);
    actAs($world['teacher']);
    $before = memory_get_usage();

    $export = app(ExportManager::class)->request(app(AnalyticsDefinitions::class)->report('subject-performance'), new ReportRequest, 'csv', analytics()->context(), queue: false);

    expect($export->rows)->toBe(303)->and(memory_get_peak_usage() - $before)->toBeLessThan(64 * 1024 * 1024);
});

it('caps high-cardinality groupings without loading every group', function (): void {
    seedBulk($this->world, 3000, 800);
    actAs($this->world['teacher']);

    $rows = analytics()->query(new AnalyticsQuery(app(AnalyticsDefinitions::class)->dataset('assessment-results'), [Measure::count()], ['subject_id']));

    expect($rows->rows)->toHaveCount(500)->and($rows->truncated)->toBeTrue();
});

it('serves repeated dashboards from the cache', function (): void {
    seedBulk($this->world, 5000, 20);
    actAs($this->world['teacher']);
    analytics()->dashboard('academic-overview');
    $queries = queriesDuring(static fn () => analytics()->dashboard('academic-overview'));

    expect(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'assessment_results')))->toBe([]);
});
