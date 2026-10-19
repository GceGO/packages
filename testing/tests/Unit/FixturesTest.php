<?php

declare(strict_types=1);

use Gcego\Testing\Exceptions\InvalidFixtureException;
use Gcego\Testing\Fixtures\Workspace;
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Membership;
use Gcego\Testing\Tests\Fixtures\Models\User;

it('creates isolated workspaces and removes them on cleanup', function (): void {
    $fixtures = GcegoTest::fixtures();
    $first = $fixtures->workspace();
    $second = $fixtures->workspace();
    $file = $first->php('Definitions/one.php', "['a' => 1]");

    expect($first->root)->not->toBe($second->root)
        ->and($first->root)->toStartWith(realpath(sys_get_temp_dir()))
        ->and($first->root)->toContain('gcego-testing-'.getmypid().'-')
        ->and(require $file)->toBe(['a' => 1]);

    $fixtures->cleanup();

    expect(is_dir($first->root))->toBeFalse()->and(is_dir($second->root))->toBeFalse()->and($fixtures->workspaces())->toBe([]);
});

it('refuses paths outside the workspace', function (): void {
    $workspace = Workspace::create();

    expect(fn () => $workspace->file('../escape.php', 'x'))->toThrow(InvalidFixtureException::class)
        ->and(fn () => $workspace->path('/etc/passwd'))->toThrow(InvalidFixtureException::class);

    $workspace->remove();
});

it('runs cleanup callbacks in reverse order, even after a failure', function (): void {
    $calls = [];
    $fixtures = GcegoTest::fixtures();
    $fixtures->onCleanup(function () use (&$calls): void {
        $calls[] = 'first';
    });
    $fixtures->onCleanup(function () use (&$calls): void {
        $calls[] = 'second';

        throw new RuntimeException('cleanup failed');
    });

    expect(fn () => $fixtures->cleanup())->toThrow(RuntimeException::class, 'cleanup failed')
        ->and($calls)->toBe(['second', 'first']);
});

it('creates deterministic users with the application model', function (): void {
    $users = GcegoTest::fixtures()->users();
    $first = $users->create();
    $second = $users->create(['name' => 'Named']);

    expect($first)->toBeInstanceOf(User::class)
        ->and($first->getAttribute('name'))->toBe('Test User 1')
        ->and($first->getAttribute('email'))->toBe('user1@example.test')
        ->and($second->getAttribute('name'))->toBe('Named')
        ->and(password_verify('password', (string) $first->getAttribute('password')))->toBeTrue();
});

it('creates tenants and memberships through the application provider', function (): void {
    $tenants = GcegoTest::fixtures()->tenants();
    $tenant = $tenants->create();
    $user = GcegoTest::fixtures()->users()->create();
    $tenants->join($user, $tenant, ['administrator']);

    expect($tenant->key)->toBe('1')
        ->and(Membership::query()->where('user_id', $user->getAuthIdentifier())->value('role'))->toBe('administrator');

    GcegoTest::tenant($tenant)->assertCanAccessTenant(user: $user);
});

it('explains what is missing when no tenant provider is configured', function (): void {
    $this->reboot(['gcego-testing.fixtures.tenants' => null]);

    expect(fn () => GcegoTest::fixtures()->tenants()->create())->toThrow(InvalidFixtureException::class, 'TenantFixtureProvider');
});

it('builds representative definitions that validate', function (string $method): void {
    $definition = GcegoTest::fixtures()->definitions()->{$method}(...($method === 'resource' || $method === 'module' ? ['things', User::class] : []));

    GcegoTest::component($definition)->assertValid();
})->with(['textInput', 'select', 'form', 'column', 'table', 'infolist', 'action', 'resource', 'stat', 'layout', 'navigation', 'page', 'module', 'panel']);
