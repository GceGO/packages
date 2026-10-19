<?php

declare(strict_types=1);

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Authorizer;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PasswordConfirmed;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\Requirements\Rule;
use Gcego\Security\Requirements\VerifiedEmail;
use Gcego\Security\Tests\Fixtures\Models\Report;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    $this->editor = User::query()->create(['name' => 'Editor', 'email_verified_at' => now()]);
});

it('allows and denies through the model policy', function (): void {
    $draft = $this->world['subjects']['draft'];
    $this->actingAs($this->world['teacher']);

    expect(security()->authorize('viewAny', Subject::class)->allowed)->toBeTrue()
        ->and(security()->authorize('view', $this->world['subjects']['algebra'])->allowed)->toBeTrue()
        ->and(security()->authorize('view', $draft)->reason)->toBe(DenialReason::PolicyDenied)
        ->and(security()->can('update', $draft))->toBeFalse();

    $this->actingAs($this->editor);
    newRequest();

    expect(security()->can('update', $draft))->toBeTrue();
});

it('denies guests unless a policy explicitly accepts them', function (): void {
    Gate::define('guest-friendly', fn (?User $user): bool => true);

    expect(security()->authorize('viewAny', Subject::class)->reason)->toBe(DenialReason::Unauthenticated)
        ->and(security()->authorize('guest-friendly')->allowed)->toBeTrue()
        ->and(security()->check(new Authenticated)->reason)->toBe(DenialReason::Unauthenticated);
});

it('denies abilities and models nobody defines', function (): void {
    $this->actingAs($this->world['teacher']);

    expect(security()->authorize('reports.view')->reason)->toBe(DenialReason::NoPolicy)
        ->and(security()->authorize('view', Report::query()->create(['title' => 'Q1']))->reason)->toBe(DenialReason::NoPolicy)
        ->and(security()->check()->reason)->toBe(DenialReason::NoRequirement);
});

it('keeps custom policy messages and not-found responses', function (): void {
    $this->actingAs($this->editor);
    $subject = $this->world['subjects']['algebra'];

    expect(security()->authorize('delete', $subject)->message())->toBe('Only curriculum owners may delete subjects.')
        ->and(security()->authorize('restore', $subject)->status)->toBe(404)
        ->and(fn () => security()->authorize('delete', $subject)->authorize())->toThrow(AccessDeniedException::class, 'Only curriculum owners');
});

it('authorizes on the configured guard', function (): void {
    // Signed in on "admin" only: the default ("web") actor is a guest.
    Auth::guard('admin')->setUser($this->world['teacher']);

    expect(security()->context()->isGuest())->toBeTrue()
        ->and(security()->context()->guard === 'web' && security()->authorize('viewAny', Subject::class)->denied())->toBeTrue()
        ->and(security()->context()->guard)->toBe('web');

    $this->rebuildApplication(['gcego-security.authorization.guard' => 'admin']);
    $user = User::query()->create(['name' => 'Admin user']);
    $this->actingAs($user, 'admin');

    expect(security()->context()->guard)->toBe('admin')
        ->and(security()->context()->userKey())->toBe($user->id)
        ->and(security()->check(new Authenticated('admin'))->allowed)->toBeTrue()
        ->and(security()->check(new Authenticated('web'))->reason)->toBe(DenialReason::WrongGuard);
});

it('requires every requirement to allow', function (): void {
    Gate::define('subjects.view', fn (User $user): bool => true);
    $this->actingAs($this->world['teacher']);

    expect(security()->check(new Permission('subjects.view'), new Ability('viewAny', Subject::class))->allowed)->toBeTrue()
        ->and(security()->check(new Permission('subjects.view'), new Permission('subjects.delete'))->reason)->toBe(DenialReason::MissingPermission)
        ->and(security()->check(new PublicAccess, new Permission('subjects.delete'))->allowed)->toBeFalse();
});

it('respects email verification and password confirmation like Laravel and Sentinel', function (): void {
    $unverified = User::query()->create(['name' => 'New']);
    $this->actingAs($unverified);

    expect(security()->check(new VerifiedEmail)->reason)->toBe(DenialReason::EmailNotVerified);

    $this->actingAs($this->world['teacher']);
    newRequest();

    expect(security()->check(new VerifiedEmail)->allowed)->toBeTrue();

    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);

    expect(security()->check(new PasswordConfirmed)->reason)->toBe(DenialReason::PasswordNotConfirmed);

    session()->put('auth.password_confirmed_at', time() - 60);

    expect(security()->check(new PasswordConfirmed)->allowed)->toBeTrue()
        ->and(security()->check(new PasswordConfirmed(30))->reason)->toBe(DenialReason::PasswordNotConfirmed);
});

it('evaluates custom rules and custom authorizers only when registered', function (): void {
    $this->actingAs($this->world['teacher']);

    expect(security()->check(new Rule('name is Teacher', fn (AuthorizationContext $context): bool => $context->user?->getAuthIdentifier() === $this->world['teacher']->id))->allowed)->toBeTrue()
        ->and(security()->check(new Rule('never', fn (): string => 'yes'))->reason)->toBe(DenialReason::RuleDenied) // @phpstan-ignore argument.type
        ->and(security()->authorize('reports.view')->allowed)->toBeFalse();

    security()->extend(new class implements Authorizer
    {
        public function decide(string $ability, mixed $resource, AuthorizationContext $context): ?AuthorizationDecision
        {
            return $ability === 'reports.view' ? AuthorizationDecision::allow($ability) : null;
        }
    });
    newRequest();

    expect(security()->authorize('reports.view')->allowed)->toBeTrue()
        ->and(security()->authorize('reports.delete')->allowed)->toBeFalse();
});

it('adds no super-administrator bypass of its own', function (): void {
    $root = User::query()->create(['name' => 'Root']);
    $this->actingAs($root);

    expect(security()->authorize('update', $this->world['subjects']['algebra'])->allowed)->toBeFalse()
        ->and(security()->permission('anything.at.all')->allowed)->toBeFalse()
        ->and(security()->permission('grades.update', tenant: true)->allowed)->toBeFalse();
});

it('applies an application Gate::before rule only to Gate checks, never to tenant permissions', function (): void {
    $root = User::query()->create(['name' => 'Root']);
    Gate::before(fn (User $user): ?bool => $user->name === 'Root' ? true : null);
    $this->actingAs($root);

    expect(security()->permission('platform.anything')->allowed)->toBeTrue()
        ->and(security()->authorize('update', $this->world['subjects']['algebra'])->allowed)->toBeTrue()
        ->and(security()->permission('grades.update', tenant: true)->reason)->toBe(DenialReason::TenantRequired);
});
