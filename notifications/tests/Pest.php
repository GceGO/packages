<?php

declare(strict_types=1);

use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\NotificationViewer;
use Gcego\Notifications\Notification;
use Gcego\Notifications\NotificationManager;
use Gcego\Notifications\Tests\Fixtures\Models\Membership;
use Gcego\Notifications\Tests\Fixtures\Models\Subject;
use Gcego\Notifications\Tests\Fixtures\Models\Tag;
use Gcego\Notifications\Tests\Fixtures\Models\Topic;
use Gcego\Notifications\Tests\Fixtures\Models\User;
use Gcego\Notifications\Tests\TestCase;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security', 'Browser');

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

function notifications(): NotificationManager
{
    return app(NotificationManager::class);
}

function panels(): PanelManager
{
    return app(PanelManager::class);
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
    // A fresh request: PHP-FPM starts every request with empty Livewire state.
    app('livewire')->flushState();
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
 * Register a panel at runtime and route it (discovered panels are routed at boot).
 */
function routePanel(Panel $panel): PanelDescriptor
{
    panels()->register($panel);
    $descriptor = panels()->descriptor($panel->panelName());
    app(PanelRouteRegistrar::class)->registerPanel(app('router'), $descriptor);

    return $descriptor;
}

/**
 * The text of a response, whitespace squished.
 */
function html(TestResponse $response): string
{
    return squish((string) $response->getContent());
}

/**
 * The toasts the ToastHost of $html starts with.
 *
 * @return list<array<string, mixed>>
 */
function initialToasts(string $html): array
{
    $state = toastState($html);

    return is_array($state['initial'] ?? null) ? $state['initial'] : [];
}

/**
 * @return array<string, mixed>
 */
function toastState(string $html): array
{
    preg_match('/x-data="gcegoToasts\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $match);
    $json = json_decode('"'.str_replace('\\\'', "'", html_entity_decode($match[1] ?? '', ENT_QUOTES)).'"');
    $state = is_string($json) ? json_decode($json, true) : null;

    return is_array($state) ? $state : [];
}

/**
 * Store a notification for $recipient right away (database channel, no queue).
 */
function stored(Model $recipient, string $title = 'Hello', ?Closure $configure = null): string
{
    $builder = Notification::make()->title($title)->database()->now();
    $result = ($configure === null ? $builder : $configure($builder))->sendTo($recipient);

    return $result->channel('database')[0]->notification;
}

function viewer(Model $user, ?string $tenant = '1', ?string $panel = null): NotificationViewer
{
    return new NotificationViewer($user, $tenant, $panel);
}

function repository(): DatabaseNotificationRepository
{
    return app(DatabaseNotificationRepository::class);
}
