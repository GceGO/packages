<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutManager;
use Gcego\Layouts\Tests\Fixtures\Models\Membership;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Layouts\Tests\Fixtures\Models\User;
use Gcego\Layouts\Tests\TestCase;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Institution 1: Amina (f), Bello (m), Chidi (m), Dora (f). Institution 2: two students never shown to institution 1.
 *
 * @return array{teacher: User, bursar: User, south: User}
 */
function world(): array
{
    Date::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $users = [];

    foreach (['teacher' => 'Teacher', 'bursar' => 'Bursar'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $users['south'] = User::query()->create(['name' => 'South']);
    Membership::query()->create(['user_id' => $users['south']->id, 'institution_id' => 2]);

    foreach ([['Amina', 1, 'f', '2026-02-10'], ['Bello', 2, 'm', '2026-03-05'], ['Chidi', 2, 'm', '2026-03-20'], ['Dora', 3, 'f', '2025-09-01']] as [$name, $level, $gender, $at]) {
        Student::query()->create(['name' => $name, 'level' => $level, 'gender' => $gender, 'enrolled_at' => $at.' 08:00:00', 'national_id' => 'NID-'.$name, 'institution_id' => 1]);
    }

    foreach (['South One', 'South Two'] as $name) {
        Student::query()->create(['name' => $name, 'gender' => 'f', 'enrolled_at' => '2026-03-01 08:00:00', 'national_id' => 'NID-SOUTH', 'institution_id' => 2]);
    }

    return $users;
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

function layouts(): LayoutManager
{
    return app(LayoutManager::class);
}

/**
 * Whitespace-collapsed HTML of a registered layout.
 */
function layoutHtml(string $name, string|int|null $record = null, bool $live = false): string
{
    return squish(layouts()->render($name, $record, $live)->toHtml());
}

/**
 * Whitespace-collapsed HTML of a trusted (unregistered) component render.
 */
function render(LayoutComponent $component, RenderMode $mode = RenderMode::Static): string
{
    return squish(app(Runtime::class)->render($component, $mode)->toHtml());
}

function squish(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
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
