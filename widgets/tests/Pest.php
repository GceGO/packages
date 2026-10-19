<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Security\SecurityManager;
use Gcego\Widgets\Tests\Fixtures\Models\ActivityLog;
use Gcego\Widgets\Tests\Fixtures\Models\Membership;
use Gcego\Widgets\Tests\Fixtures\Models\Payment;
use Gcego\Widgets\Tests\Fixtures\Models\Result;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Models\User;
use Gcego\Widgets\Tests\TestCase;
use Gcego\Widgets\WidgetManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Institution 1: 4 students (3 enrolled this year), payments 25 000 + 15 000, results, activity.
 * Institution 2: 2 students and a 99 999 payment that must never show up for institution 1.
 *
 * @return array{teacher: User, bursar: User, south_user: User}
 */
function world(): array
{
    Date::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $users = [];

    foreach (['teacher' => 'Teacher', 'bursar' => 'Bursar'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $south = User::query()->create(['name' => 'Bursar']);
    Membership::query()->create(['user_id' => $south->id, 'institution_id' => 2]);

    foreach ([['Amina', 1, 'f', '2026-02-10'], ['Bello', 2, 'm', '2026-03-05'], ['Chidi', 2, 'm', '2026-03-20'], ['Dora', 3, 'f', '2025-09-01']] as [$name, $level, $gender, $at]) {
        Student::query()->create(['name' => $name, 'level' => $level, 'gender' => $gender, 'enrolled_at' => $at.' 08:00:00', 'national_id' => 'NID-'.$name, 'institution_id' => 1]);
    }

    Student::query()->create(['name' => 'South One', 'level' => 1, 'enrolled_at' => '2026-03-01 08:00:00', 'institution_id' => 2]);
    Student::query()->create(['name' => 'South Two', 'level' => 1, 'enrolled_at' => '2026-03-02 08:00:00', 'institution_id' => 2]);

    Payment::query()->create(['amount' => '25000.00', 'method' => 'cash', 'paid_at' => '2026-03-01 10:00:00', 'institution_id' => 1]);
    Payment::query()->create(['amount' => '15000.00', 'method' => 'mobile', 'paid_at' => '2026-04-01 10:00:00', 'institution_id' => 1]);
    Payment::query()->create(['amount' => '99999.00', 'method' => 'cash', 'paid_at' => '2026-04-01 10:00:00', 'institution_id' => 2]);

    Result::query()->create(['passed' => false, 'graded_at' => '2026-05-01 10:00:00', 'institution_id' => 1]);
    Result::query()->create(['passed' => false, 'graded_at' => '2026-05-02 10:00:00', 'institution_id' => 1]);
    Result::query()->create(['passed' => false, 'graded_at' => '2025-05-02 10:00:00', 'institution_id' => 1]);

    foreach (['Term opened', 'Fees reminder sent', 'Timetable published'] as $index => $description) {
        $log = new ActivityLog(['description' => $description, 'causer_id' => $users['teacher']->id, 'institution_id' => 1]);
        $log->created_at = CarbonImmutable::parse('2026-06-0'.($index + 1).' 09:00:00');
        $log->save();
    }

    $log = new ActivityLog(['description' => 'South secret', 'institution_id' => 2]);
    $log->created_at = CarbonImmutable::parse('2026-06-10 09:00:00');
    $log->save();

    return [...$users, 'south_user' => $south];
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

function widgets(): WidgetManager
{
    return app(WidgetManager::class);
}

/**
 * @param  array<string, mixed>  $state
 */
function widgetHtml(string $name, array $state = [], string|int|null $record = null): string
{
    return (string) preg_replace('/\s+/', ' ', widgets()->render($name, $state, $record)->toHtml());
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

function statusOf(Closure $callback): ?int
{
    try {
        $callback();
    } catch (HttpExceptionInterface $exception) {
        return $exception->getStatusCode();
    } catch (AuthorizationException $exception) {
        return $exception->status() ?? 403;
    }

    return null;
}
