<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Pages\PageManager;
use Gcego\Pages\Tests\Fixtures\Models\Document;
use Gcego\Pages\Tests\Fixtures\Models\Membership;
use Gcego\Pages\Tests\Fixtures\Models\Note;
use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Models\Topic;
use Gcego\Pages\Tests\Fixtures\Models\User;
use Gcego\Pages\Tests\TestCase;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Institution 1: Chemistry (1), Physics (2), Locked (3). Institution 2: Secret Biology (4).
 * Admin, Editor, Reader and Visitor (no permissions) are members of institution 1; South of institution 2; Stranger of none.
 *
 * Documents (UUID keys), notes (ULID keys) and topics (slug keys) belong to institution 1, except one foreign document.
 *
 * @return array{admin: User, editor: User, reader: User, south: User, visitor: User, stranger: User, subjects: list<Subject>, documents: list<Document>, notes: list<Note>, topics: list<Topic>}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1], 'editor' => ['Editor', 1], 'reader' => ['Reader', 1], 'visitor' => ['Visitor', 1], 'south' => ['South', 2], 'stranger' => ['Stranger', null]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name]);

        if ($institution !== null) {
            Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
        }
    }

    $subjects = [];

    foreach ([['Chemistry', 'CHE', 1], ['Physics', 'PHY', 1], ['Locked', 'LOC', 1], ['Secret Biology', 'BIO', 2]] as [$name, $code, $institution]) {
        $subject = new Subject(['name' => $name, 'code' => $code]);
        $subject->forceFill(['institution_id' => $institution, 'internal_notes' => 'SECRET-NOTE-'.$code])->save();
        $subjects[] = $subject;
    }

    $documents = [Document::query()->create(['name' => 'Syllabus', 'code' => 'SYL', 'institution_id' => 1]), Document::query()->create(['name' => 'Foreign syllabus', 'code' => 'FOR', 'institution_id' => 2])];
    $notes = [Note::query()->create(['name' => 'Reminder', 'institution_id' => 1])];
    $topics = [Topic::query()->create(['slug' => 'organic-chemistry', 'name' => 'Organic chemistry', 'code' => 'ORG', 'institution_id' => 1])];

    return [...$users, 'subjects' => $subjects, 'documents' => $documents, 'notes' => $notes, 'topics' => $topics];
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
 * The session that selects a tenant for HTTP requests (gcego.tenant middleware).
 *
 * @return array<string, string>
 */
function tenantSession(int $institution = 1): array
{
    return [app(SecurityConfig::class)->tenantSessionKey => (string) $institution];
}

function pages(): PageManager
{
    return app(PageManager::class);
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
    } catch (AuthenticationException) {
        return 401;
    }

    return null;
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
function livewireUpdate(string $snapshot, array $calls = [], array $updates = [], ?int $institution = null): TestResponse
{
    // A new request: request-scoped services (security context, decisions) start fresh, as in production.
    app()->forgetScopedInstances();
    $test = $institution === null ? test() : test()->withSession(tenantSession($institution));

    return $test->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => array_map(static fn (array $call): array => ['path' => '', ...$call], $calls)]],
    ]);
}

/**
 * Sign in for HTTP requests, with a tenant selected in the session.
 */
function asMember(User $user, int $institution = 1): object
{
    // Every request starts with fresh request-scoped services, as in production.
    app()->forgetScopedInstances();

    return test()->actingAs($user)->withSession(tenantSession($institution));
}

/**
 * Measured numbers are written to build/performance.json (not asserted
 * beyond generous bounds: CI machines differ).
 *
 * @param  array<string, int|float|string>  $numbers
 */
function recordPerformance(array $numbers): void
{
    $file = dirname(__DIR__).'/build/performance.json';
    @mkdir(dirname($file), 0777, true);
    $existing = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    file_put_contents($file, json_encode([...(is_array($existing) ? $existing : []), ...$numbers], JSON_PRETTY_PRINT));
}
