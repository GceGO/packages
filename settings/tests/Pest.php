<?php

declare(strict_types=1);

use Gcego\Security\SecurityConfig;
use Gcego\Security\SecurityManager;
use Gcego\Settings\SettingsManager;
use Gcego\Settings\Tests\Fixtures\Models\Membership;
use Gcego\Settings\Tests\Fixtures\Models\User;
use Gcego\Settings\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security');

/**
 * Admin: platform settings manager, and administrator of institution 1.
 * Principal: administrator of institution 1 (tenant permission "settings.manage").
 * Teacher: member of institution 1 without settings rights.
 * South: administrator of institution 2.
 * Security: the security officer (Gate "security.manage").
 *
 * @return array{admin: User, principal: User, teacher: User, south: User, security: User}
 */
function world(): array
{
    $users = [];

    foreach (['admin' => ['Admin', [1 => ['settings.manage', 'gcego-settings.export', 'gcego-settings.import']]], 'principal' => ['Principal', [1 => ['settings.manage']]], 'teacher' => ['Teacher', [1 => []]], 'south' => ['South', [2 => ['settings.manage']]], 'security' => ['Security', [1 => []]]] as $key => [$name, $memberships]) {
        $users[$key] = User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test']);

        foreach ($memberships as $institution => $permissions) {
            Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => $institution, 'permissions' => $permissions]);
        }
    }

    return $users;
}

function settings(): SettingsManager
{
    return app(SettingsManager::class);
}

/**
 * Act as a user (optionally in a tenant) for direct calls: a new "request".
 */
function actAs(?User $user, ?int $institution = null): void
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
function livewireUpdate(string $snapshot, array $calls = [], array $updates = [], ?int $institution = 1): TestResponse
{
    app('livewire')->flushState();
    app()->forgetScopedInstances();

    return test()->withSession($institution === null ? [] : tenantSession($institution))->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => array_map(static fn (array $call): array => ['path' => '', ...$call], $calls)]],
    ]);
}
