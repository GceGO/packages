<?php

declare(strict_types=1);

use Gcego\Core\Gcego;
use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Integration\SecurityDataAuthorizer;
use Gcego\Security\Permissions\DecisionCache;
use Gcego\Security\SecurityManager;
use Gcego\Security\SecurityServiceProvider;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

it('takes part in the gcego/core package lifecycle', function (): void {
    expect(app(Gcego::class)->bootResult()?->packages)->toContain('gcego/security')
        ->and(app(Gcego::class)->hasCapability('security.tenancy'))->toBeTrue()
        ->and(ServiceProvider::pathsToPublish(SecurityServiceProvider::class, 'gcego-security-config'))->toHaveCount(1);
});

it('neither resolves users nor queries the database while booting', function (): void {
    $this->refreshApplication();

    expect(DB::getConnections())->toBe([])
        ->and(app('auth')->hasResolvedGuards())->toBeFalse();
});

it('binds request state as scoped instances, reset between requests and jobs', function (string $service): void {
    $first = app($service);
    expect(app($service))->toBe($first);

    app()->forgetScopedInstances();

    expect(app($service))->not->toBe($first);
})->with([TenantContext::class, DecisionCache::class, ContextFactory::class, AuthorizationEngine::class]);

it('never reuses a decision for another user in a long-running worker', function (): void {
    $world = world();
    Gate::define('subjects.view', fn (User $user): bool => $user->name === 'Teacher');

    $this->actingAs($world['teacher']);
    expect(security()->permission('subjects.view')->allowed)->toBeTrue();

    // Same worker, same scoped instances, different user.
    $this->actingAs($world['admin']);
    expect(security()->permission('subjects.view')->allowed)->toBeFalse()
        ->and(app(DecisionCache::class)->count())->toBe(2);
});

it('keeps application bindings of the runtime and data authorizers', function (): void {
    $custom = new class implements RenderAuthorizer
    {
        public function authorize(DefinitionDescriptor $descriptor): void {}
    };
    $this->app->singleton(RenderAuthorizer::class, fn () => $custom);

    expect(app(RenderAuthorizer::class))->toBe($custom)
        ->and(app(DataAuthorizer::class))->toBeInstanceOf(SecurityDataAuthorizer::class)
        ->and(app(SecurityManager::class))->toBe(app(SecurityManager::class));
});

it('does not read env() at runtime', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $tokens = token_get_all((string) file_get_contents($file->getPathname()));

            expect(array_filter($tokens, fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'env'))->toBe([], $file->getPathname());
        }
    }
});
