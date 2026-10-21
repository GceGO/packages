<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Tests\Fixtures\Models\Membership;
use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\Tests\Fixtures\Models\User;
use Gcego\Actions\Tests\TestCase;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Users Reader, Editor and Admin, members of institution 1 (Admin also of 2),
 * and subjects in both institutions (one trashed, one locked).
 *
 * @return array{reader: User, editor: User, admin: User, subjects: array<string, Subject>}
 */
function world(): array
{
    $users = [];

    foreach (['reader' => 'Reader', 'editor' => 'Editor', 'admin' => 'Admin'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    Membership::query()->create(['user_id' => $users['admin']->id, 'institution_id' => 2]);

    $subjects = [];

    foreach ([
        'algebra' => ['name' => 'Algebra', 'code' => 'MTH', 'institution_id' => 1],
        'biology' => ['name' => 'Biology', 'code' => 'BIO', 'institution_id' => 1],
        'locked' => ['name' => 'Locked', 'code' => 'LOCKED', 'institution_id' => 1],
        'south' => ['name' => 'South Physics', 'code' => 'PHY', 'institution_id' => 2],
        'trashed' => ['name' => 'Old Latin', 'code' => 'LAT', 'institution_id' => 1],
    ] as $key => $attributes) {
        $subject = new Subject($attributes);
        $subject->institution_id = $attributes['institution_id'];
        $subject->save();
        $subjects[$key] = $subject;
    }

    $subjects['trashed']->delete();

    return [...$users, 'subjects' => $subjects];
}

/**
 * Sign in and enter institution 1 (or another) like a request through gcego.tenant.
 */
function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
}

/**
 * @param  array<string, mixed>  $arguments
 */
function act(string $action, array $arguments = [], string $definition = 'blueprint:subjects'): ActionResult
{
    return app(ActionManager::class)->execute(new ActionRequest($definition, $action, ...$arguments));
}

/**
 * Execute with confirmation: request the token, then confirm.
 *
 * @param  array<string, mixed>  $arguments
 */
function confirmAndAct(string $action, array $arguments = [], string $definition = 'blueprint:subjects'): ActionResult
{
    $first = act($action, $arguments, $definition);

    if ($first->confirmation === null) {
        return $first;
    }

    return act($action, [...$arguments, 'confirmation' => $first->confirmation['token']], $definition);
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
