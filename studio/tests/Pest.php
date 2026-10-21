<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Gcego\Studio\Studio;
use Gcego\Studio\Tests\Fixtures\Models\Membership;
use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Gcego\Studio\Tests\Fixtures\Models\User;
use Gcego\Studio\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

/**
 * Institution 1: Admin, Editor (both may enter the admin panel), Reader (a member only); subjects Chemistry, Physics.
 * Institution 2: South; subject Biology.
 *
 * @return array{admin: User, editor: User, reader: User, south: User, chemistry: Subject, physics: Subject, biology: Subject}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', 1], 'editor' => ['Editor', 1], 'reader' => ['Reader', 1], 'south' => ['South', 2]] as $key => [$name, $institution]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution]);
    }

    $subjects = [];

    foreach (['chemistry' => ['Chemistry', 'CHE', 1], 'physics' => ['Physics', 'PHY', 1], 'biology' => ['Biology', 'BIO', 2]] as $key => [$name, $code, $institution]) {
        $subject = new Subject(['name' => $name, 'code' => $code]);
        $subject->forceFill(['institution_id' => $institution])->save();
        $subjects[$key] = $subject;
    }

    return [...$users, ...$subjects];
}

function studio(): Studio
{
    return app(Studio::class);
}

/**
 * @return array<string, string>
 */
function tenantSession(int $institution = 1): array
{
    return [app(SecurityConfig::class)->tenantSessionKey => (string) $institution];
}

function html(TestResponse $response): string
{
    return trim((string) preg_replace('/\s+/', ' ', (string) $response->getContent()));
}

/**
 * Writes a file in the test workspace.
 */
function workspaceFile(string $path, string $contents): string
{
    $file = TestCase::workspace($path);
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, $contents);

    return $file;
}

function asMember(User $user, int $institution = 1): TestCase
{
    app()->forgetScopedInstances();

    return test()->actingAs($user)->withSession(tenantSession($institution));
}

function actAs(User $user, int $institution = 1): void
{
    test()->actingAs($user);
    app()->forgetScopedInstances();
    app(SecurityManager::class)->enterTenant((string) $institution);
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
