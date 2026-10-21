<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Tests\Fixtures\Models\Application;
use Gcego\Workflows\Tests\Fixtures\Models\Membership;
use Gcego\Workflows\Tests\Fixtures\Models\User;
use Gcego\Workflows\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Illuminate\Foundation\Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

/**
 * Institution 1: Admin (manager), Editor (initiator), Reviewer1–3, Visitor; applications "Alpha", "Beta", "Locked".
 * Institution 2: South; application "Southern".
 *
 * @return array{admin: User, editor: User, reviewer1: User, reviewer2: User, reviewer3: User, visitor: User, south: User, alpha: Application, beta: Application, locked: Application, southern: Application}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1], 'editor' => ['Editor', 1], 'reviewer1' => ['Reviewer1', 1], 'reviewer2' => ['Reviewer2', 1], 'reviewer3' => ['Reviewer3', 1], 'visitor' => ['Visitor', 1], 'south' => ['South', 2]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
    }

    $applications = [];

    foreach (['alpha' => 'Alpha', 'beta' => 'Beta', 'locked' => 'Locked', 'southern' => 'Southern'] as $key => $name) {
        $applications[$key] = Application::query()->create(['name' => $name]);
    }

    return [...$users, ...$applications];
}

function engine(): WorkflowEngine
{
    return app(WorkflowEngine::class);
}

/**
 * Act as a user in a tenant, for direct (non-HTTP) calls; a new "request".
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
 * Starts "registration" for Alpha as the Editor and submits it into review (as Reviewer1).
 *
 * @param  array<string, mixed>  $world
 */
function inReview(array $world, string $application = 'alpha'): WorkflowInstance
{
    actAs($world['editor']);
    $instance = engine()->start('registration', $world[$application], $world['editor']);
    engine()->transition($instance, 'submit', $world['editor']);
    actAs($world['reviewer1']);
    engine()->transition($instance, 'review', $world['reviewer1']);

    return $instance->refresh();
}

/**
 * The open approval or task of a person in an instance.
 */
function taskOf(WorkflowInstance $instance, ?User $user, ?string $name = null): WorkflowTask
{
    $query = WorkflowTask::query()->where('instance_id', $instance->id)->open()->when($name !== null, static fn ($query) => $query->where('name', $name));
    $task = $user === null ? $query->where('pool', true)->first() : $query->where('assignee_id', (string) $user->id)->first();

    if (! $task instanceof WorkflowTask) {
        throw new RuntimeException('No open task.');
    }

    return $task;
}

function decideAs(User $user, WorkflowInstance $instance, Decision $decision, ?string $comment = null, ?string $name = null, bool $pool = false): void
{
    actAs($user);
    engine()->decide(taskOf($instance, $pool ? null : $user, $name), $decision, $user, $comment);
}

/**
 * @return list<string>
 */
function historyOf(WorkflowInstance $instance): array
{
    return array_values($instance->events()->orderBy('sequence')->pluck('type')->all());
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

/**
 * The decoded wire:snapshot of the first Livewire component named $name in $html.
 */
function snapshotOf(string $html, string $name): string
{
    preg_match('/wire:snapshot="([^"]+)"[^>]*wire:name="'.preg_quote($name, '/').'"/', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES);
}

/**
 * A real Livewire update request (POST to the update endpoint) for one component.
 *
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
