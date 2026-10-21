<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\Tests\Fixtures\Models\Membership;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Modules\Tests\Fixtures\Models\Tag;
use Gcego\Modules\Tests\Fixtures\Models\Topic;
use Gcego\Modules\Tests\Fixtures\Models\User;
use Gcego\Modules\Tests\TestCase;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
 * Institution 1: Chemistry (1) with topics Organic (1) and Hidden (2) and tag Core (1); Physics (2); Locked (3).
 * Institution 2: Biology (4) with topic Cells (3) and tag Foreign (2).
 * Admin, Editor, Reader and Visitor are members of institution 1; South of institution 2.
 *
 * @return array{admin: User, editor: User, reader: User, visitor: User, south: User, subjects: list<Subject>, topics: list<Topic>, tags: list<Tag>}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1], 'editor' => ['Editor', 1], 'reader' => ['Reader', 1], 'visitor' => ['Visitor', 1], 'south' => ['South', 2]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
    }

    $subjects = [];

    foreach ([['Chemistry', 'CHE', 1], ['Physics', 'PHY', 1], ['Locked', 'LOC', 1], ['Biology', 'BIO', 2]] as [$name, $code, $institution]) {
        $subject = new Subject(['name' => $name, 'code' => $code]);
        $subject->forceFill(['institution_id' => $institution, 'internal_notes' => 'SECRET-'.$code])->save();
        $subjects[] = $subject;
    }

    $topics = [];

    foreach ([['Organic', 1, 1], ['Hidden', 1, 1], ['Cells', 4, 2]] as [$name, $subject, $institution]) {
        $topic = new Topic(['name' => $name]);
        $topic->forceFill(['subject_id' => $subject, 'institution_id' => $institution])->save();
        $topics[] = $topic;
    }

    $tags = [];

    foreach ([['Core', 1], ['Foreign', 2], ['Elective', 1]] as [$name, $institution]) {
        $tag = new Tag(['name' => $name]);
        $tag->forceFill(['institution_id' => $institution])->save();
        $tags[] = $tag;
    }

    $subjects[0]->tags()->attach($tags[0]->id);
    $subjects[3]->tags()->attach($tags[1]->id);

    return [...$users, 'subjects' => $subjects, 'topics' => $topics, 'tags' => $tags];
}

function modules(): ModuleManager
{
    return app(ModuleManager::class);
}

/**
 * Act as a user in a tenant, for direct (non-HTTP) calls.
 */
function actAs(?User $user, ?int $institution = 1): void
{
    if ($user !== null) {
        test()->actingAs($user);
    }

    app()->forgetScopedInstances();

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

/**
 * Sign in for HTTP requests, with a tenant selected in the session.
 */
function asMember(User $user, int $institution = 1): object
{
    app()->forgetScopedInstances();

    return test()->actingAs($user)->withSession(tenantSession($institution));
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

/**
 * The decoded wire:snapshot of the first Livewire component named $name in $html.
 */
function snapshotOf(string $html, string $name): string
{
    preg_match('/wire:snapshot="([^"]+)"[^>]*wire:name="'.preg_quote($name, '/').'"/', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES);
}

/**
 * The decoded snapshots of every Livewire component named $name in $html.
 *
 * @return list<string>
 */
function snapshotsOf(string $html, string $name): array
{
    preg_match_all('/wire:snapshot="([^"]+)"[^>]*wire:name="'.preg_quote($name, '/').'"/', $html, $matches);

    return array_map(static fn (string $snapshot): string => html_entity_decode($snapshot, ENT_QUOTES), $matches[1]);
}

/**
 * A real Livewire update request (POST to the update endpoint) for one component.
 *
 * @param  list<array{method: string, params: list<mixed>}>  $calls
 * @param  array<string, mixed>  $updates
 */
function livewireUpdate(string $snapshot, array $calls = [], array $updates = [], int $institution = 1): TestResponse
{
    app()->forgetScopedInstances();

    return test()->withSession(tenantSession($institution))->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => array_map(static fn (array $call): array => ['path' => '', ...$call], $calls)]],
    ]);
}

/**
 * @param  array<string, int|float|string>  $numbers
 */
function recordPerformance(array $numbers): void
{
    $file = dirname(__DIR__).'/build/performance.json';
    @mkdir(dirname($file), 0777, true);
    $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    file_put_contents($file, json_encode([...(is_array($existing) ? $existing : []), ...$numbers], JSON_PRETTY_PRINT));
}

/**
 * Make $uri the current request (route matched and bound), as during a page
 * request, for direct ActionManager / ModuleManager calls.
 */
function onPage(string $uri): void
{
    $request = Request::create($uri, 'GET');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(static fn () => $route);
    app()->instance('request', $request);
}

/**
 * Run a gcego/actions action of a page definition as the current user.
 *
 * @param  array<string, mixed>  $input
 */
function runAction(string $page, string $action, string|int|null $record = null, array $input = []): ActionResult
{
    $manager = app(ActionManager::class);
    $result = $manager->execute(new ActionRequest($page, $action, $record, [], $input));

    // Confirm, as the user would in the dialog.
    if ($result->status === ActionStatus::ConfirmationRequired && is_string($result->confirmation['token'] ?? null)) {
        $result = $manager->execute(new ActionRequest($page, $action, $record, [], $input, $result->confirmation['token']));
    }

    return $result;
}
