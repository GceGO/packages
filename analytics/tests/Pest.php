<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsManager;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Membership;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Analytics\Tests\Fixtures\Models\User;
use Gcego\Analytics\Tests\TestCase;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

/**
 * "Now" is 2026-03-15 12:00 UTC. Institution 1: Admin, Teacher, Student1–3, Visitor; subjects Mathematics,
 * Chemistry, Literature. Institution 2: South, SouthStudent; subject Biology.
 *
 * Results of institution 1 in the last 30 days (2026-02-14 – 2026-03-15):
 *   1 Student1 Mathematics O objective completed 80  600s 2026-03-10 09:00
 *   2 Student1 Chemistry   O structured completed 60 1200s 2026-03-11 10:00
 *   3 Student2 Mathematics O objective completed 70  300s 2026-03-11 23:30 (2026-03-12 00:30 in Douala)
 *   4 Student2 Literature  A essay     started   —   100s 2026-03-12 08:00
 *   5 Student3 Mathematics A objective completed 90  900s 2026-02-20 10:00
 *   6 Student3 Chemistry   A structured completed 50  500s 2026-03-14 15:00
 * plus a soft-deleted result (Student1, 10, 2026-03-13), an old one (Student1, Mathematics, 40, 2026-01-20)
 * and institution 2's (SouthStudent Biology 99, 2026-03-12).
 *
 * @return array<string, mixed>
 */
function world(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));
    $users = [];

    foreach (['admin' => ['Admin', 1], 'teacher' => ['Teacher', 1], 'student1' => ['Student1', 1], 'student2' => ['Student2', 1], 'student3' => ['Student3', 1], 'visitor' => ['Visitor', 1], 'south' => ['South', 2], 'southStudent' => ['SouthStudent', 2]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
    }

    $subjects = [];

    foreach (['mathematics' => ['Mathematics', 1], 'chemistry' => ['Chemistry', 1], 'literature' => ['Literature', 1], 'biology' => ['Biology', 2]] as $key => [$name, $institution]) {
        $subjects[$key] = Subject::query()->create(['name' => $name, 'institution_id' => $institution]);
    }

    $results = [
        ['student1', 'mathematics', 1, 'o', 'objective', 'completed', 80, 600, '2026-03-10 09:00:00'],
        ['student1', 'chemistry', 1, 'o', 'structured', 'completed', 60, 1200, '2026-03-11 10:00:00'],
        ['student2', 'mathematics', 1, 'o', 'objective', 'completed', 70, 300, '2026-03-11 23:30:00'],
        ['student2', 'literature', 1, 'a', 'essay', 'started', null, 100, '2026-03-12 08:00:00'],
        ['student3', 'mathematics', 1, 'a', 'objective', 'completed', 90, 900, '2026-02-20 10:00:00'],
        ['student3', 'chemistry', 1, 'a', 'structured', 'completed', 50, 500, '2026-03-14 15:00:00'],
        ['student1', 'mathematics', 1, 'o', 'objective', 'completed', 10, 50, '2026-03-13 10:00:00', true],
        ['student1', 'mathematics', 1, 'o', 'objective', 'completed', 40, 400, '2026-01-20 10:00:00'],
        ['southStudent', 'biology', 2, 'o', 'objective', 'completed', 99, 999, '2026-03-12 10:00:00'],
    ];

    foreach ($results as $row) {
        $result = AssessmentResult::query()->create([
            'user_id' => $users[$row[0]]->id, 'subject_id' => $subjects[$row[1]]->id, 'institution_id' => $row[2], 'level' => $row[3], 'kind' => $row[4],
            'status' => $row[5], 'score' => $row[6], 'time_spent' => $row[7], 'submitted_at' => $row[8], 'email' => $users[$row[0]]->email,
        ]);

        if (($row[9] ?? false) === true) {
            $result->delete();
        }
    }

    return [...$users, 'subjects' => $subjects];
}

function analytics(): AnalyticsManager
{
    return app(AnalyticsManager::class);
}

/**
 * Act as a user in a tenant (a new "request").
 */
function actAs(?User $user, ?int $institution = 1): void
{
    app()->forgetScopedInstances();
    app('auth')->forgetGuards();

    if ($user !== null) {
        test()->actingAs($user);
    }

    if ($institution !== null && $user !== null) {
        app(SecurityManager::class)->enterTenant((string) $institution);
    }
}

/**
 * @return array<string, string>
 */
function tenantSession(int $institution = 1): array
{
    return [app(SecurityConfig::class)->tenantSessionKey => (string) $institution];
}

function squish(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

function html(TestResponse $response): string
{
    return squish((string) $response->getContent());
}

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    $recording = true;
    Event::listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$queries, &$recording): void {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
    }

    return $queries;
}

function snapshotOf(string $html, string $name): string
{
    preg_match('/wire:snapshot="([^"]+)"[^>]*wire:name="'.preg_quote($name, '/').'"/', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES);
}

/**
 * @param  list<array{method: string, params: list<mixed>}>  $calls
 * @param  array<string, mixed>  $updates
 */
function livewireUpdate(string $snapshot, array $calls = [], array $updates = [], int $institution = 1): TestResponse
{
    app('livewire')->flushState();
    app()->forgetScopedInstances();

    return test()->withSession(tenantSession($institution))->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => array_map(static fn (array $call): array => ['path' => '', ...$call], $calls)]],
    ]);
}
