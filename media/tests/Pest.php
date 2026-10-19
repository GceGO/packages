<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionResult;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Media\Livewire\MediaUploader;
use Gcego\Media\MediaManager;
use Gcego\Media\Tests\Fixtures\Models\Membership;
use Gcego\Media\Tests\Fixtures\Models\Subject;
use Gcego\Media\Tests\Fixtures\Models\Tag;
use Gcego\Media\Tests\Fixtures\Models\Topic;
use Gcego\Media\Tests\Fixtures\Models\User;
use Gcego\Media\Tests\TestCase;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelManager;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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

function media(): MediaManager
{
    return app(MediaManager::class);
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
 * A real file for uploads (content decides its type, never the name).
 *
 * @param  'png'|'jpg'|'gif'|'webp'|'pdf'|'txt'|'csv'|'php'|'svg'|'html'|'exe'|'mp3'  $kind
 */
function fixtureFile(string $kind, string $name, int $width = 64, int $height = 48): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'gcego-media-fixture-');

    $contents = match ($kind) {
        'png', 'jpg', 'gif', 'webp' => (static function () use ($kind, $width, $height): string {
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));
            ob_start();
            match ($kind) {
                'png' => imagepng($image),
                'jpg' => imagejpeg($image, null, 90),
                'gif' => imagegif($image),
                'webp' => imagewebp($image),
            };

            return (string) ob_get_clean();
        })(),
        'pdf' => "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
        'txt' => "Lesson notes\nChapter one.\n",
        'csv' => "name,score\nAda,10\n",
        'php' => "<?php echo shell_exec(\$_GET['c']); ?>\n",
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script></svg>',
        'html' => '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>',
        'exe' => "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat("\x00", 64)."PE\x00\x00",
        'mp3' => "ID3\x03\x00\x00\x00\x00\x00\x0F".str_repeat("\x00", 32),
    };

    file_put_contents((string) $path, $contents);

    return new UploadedFile((string) $path, $name, null, null, true);
}

/**
 * A real Livewire temporary upload, finished by the current user (Livewire's _finishUpload call).
 */
function livewireTemporary(string $kind, string $name, bool $finish = true): TemporaryUploadedFile
{
    $path = FileUploadConfiguration::storeTemporaryFile(fixtureFile($kind, $name), FileUploadConfiguration::disk());
    $relative = ltrim(str_replace(FileUploadConfiguration::path(), '', (string) $path), '/');

    if ($finish) {
        Livewire\trigger('call', new MediaUploader, '_finishUpload', ['files', [TemporaryUploadedFile::signPath($relative)], false], null, static fn () => null, null, 0);
    }

    return TemporaryUploadedFile::createFromLivewire($relative);
}

/**
 * Run a gcego/actions action of a page as the current user (confirming when asked).
 *
 * @param  array<string, mixed>  $input
 */
function runAction(string $page, string $action, string|int|null $record = null, array $input = []): ActionResult
{
    $manager = app(ActionManager::class);
    $result = $manager->execute(new ActionRequest($page, $action, $record, [], $input));

    if ($result->status === ActionStatus::ConfirmationRequired && is_string($result->confirmation['token'] ?? null)) {
        $result = $manager->execute(new ActionRequest($page, $action, $record, [], $input, $result->confirmation['token']));
    }

    return $result;
}
