<?php

declare(strict_types=1);

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Authorization\ResourceReference;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\Exceptions\MissingAuthorizationContextException;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

it('represents allowed and denied decisions immutably', function (): void {
    $subject = new Subject(['name' => 'Algebra']);
    $subject->id = 7;
    $allowed = AuthorizationDecision::allow('update', $subject);
    $denied = AuthorizationDecision::deny('update', DenialReason::PolicyDenied, $subject);

    expect($allowed->allowed)->toBeTrue()
        ->and($allowed->authorize())->toBe($allowed)
        ->and($allowed->toArray()['resource'])->toBe(Subject::class.'#7')
        ->and($denied->denied())->toBeTrue()
        ->and($denied->status)->toBe(403)
        ->and($denied->message())->toBe('This action is unauthorized.')
        ->and($denied->toArray())->toMatchArray(['allowed' => false, 'reason' => 'policy_denied', 'status' => 403])
        ->and(fn () => $denied->allowed = true)->toThrow(Error::class); // @phpstan-ignore property.readOnlyAssignOutOfClass
});

it('throws a Laravel AuthorizationException carrying the status', function (): void {
    $decision = AuthorizationDecision::deny('view', DenialReason::NotFound, status: DenialStrategy::Conceal);

    try {
        $decision->authorize();
        $this->fail('Expected AccessDeniedException.');
    } catch (AccessDeniedException $exception) {
        expect($exception)->toBeInstanceOf(AuthorizationException::class)
            ->and($exception->status())->toBe(404)
            ->and($exception->getMessage())->toBe('Not found.')
            ->and($exception->decision())->toBe($decision);
    }
});

it('switches between 403 and 404 strategies explicitly', function (): void {
    $decision = AuthorizationDecision::deny('view', DenialReason::PolicyDenied, message: 'Only owners.');

    expect($decision->withStrategy(DenialStrategy::Conceal)->status)->toBe(404)
        ->and($decision->withStrategy(DenialStrategy::Conceal)->message())->toBe('Not found.')
        ->and($decision->withStrategy(DenialStrategy::Forbidden)->message())->toBe('Only owners.')
        ->and(AuthorizationDecision::allow('x')->withStrategy(DenialStrategy::Conceal)->allowed)->toBeTrue();
});

it('keeps policy messages and statuses from Laravel responses', function (): void {
    expect(AuthorizationDecision::fromResponse(Response::deny('No.'), 'delete', null, DenialReason::PolicyDenied, DenialStrategy::Forbidden)->message())->toBe('No.')
        ->and(AuthorizationDecision::fromResponse(Response::denyAsNotFound(), 'view', null, DenialReason::PolicyDenied, DenialStrategy::Forbidden)->status)->toBe(404)
        ->and(AuthorizationDecision::fromResponse(Response::allow(), 'view', null, DenialReason::PolicyDenied, DenialStrategy::Forbidden)->allowed)->toBeTrue()
        ->and(AuthorizationDecision::deny('x', DenialReason::MissingPermission)->toResponse()->status())->toBe(403);
});

it('describes resources without attributes', function (): void {
    $subject = new Subject(['name' => 'Secret name']);
    $subject->id = 3;

    expect(ResourceReference::of($subject)->toString())->toBe(Subject::class.'#3')
        ->and(ResourceReference::of(Subject::class)->toString())->toBe(Subject::class)
        ->and(ResourceReference::of(null)->toString())->toBe('')
        ->and(ResourceReference::of(['id' => 9, 'secret' => 'x'])->toString())->toBe('array#9')
        ->and((string) json_encode(AuthorizationDecision::deny('view', DenialReason::PolicyDenied, $subject)->toArray()))->not->toContain('Secret name');
});

it('only accepts memberships of the context user and tenant', function (): void {
    $user = new User(['name' => 'A']);
    $user->id = 1;
    $north = new Tenant('institution', 1);
    $south = new Tenant('institution', 2);

    expect(new AuthorizationContext($user, 'web', $north, new TenantMembership($north, 1)))->toBeInstanceOf(AuthorizationContext::class)
        ->and(fn () => new AuthorizationContext($user, 'web', $south, new TenantMembership($north, 1)))->toThrow(MissingAuthorizationContextException::class)
        ->and(fn () => new AuthorizationContext($user, 'web', $north, new TenantMembership($north, 2)))->toThrow(MissingAuthorizationContextException::class)
        ->and(fn () => new AuthorizationContext(null, 'web', $north, new TenantMembership($north, 1)))->toThrow(MissingAuthorizationContextException::class);
});

it('fingerprints everything a permission depends on', function (): void {
    $user = new User(['name' => 'A']);
    $user->id = 1;
    $north = new Tenant('institution', 1);
    $fingerprints = [
        AuthorizationContext::guest('web')->fingerprint(),
        (new AuthorizationContext($user, 'web'))->fingerprint(),
        (new AuthorizationContext($user, 'admin'))->fingerprint(),
        (new AuthorizationContext($user, 'web', $north))->fingerprint(),
        (new AuthorizationContext($user, 'web', $north, new TenantMembership($north, 1, ['teacher'])))->fingerprint(),
        (new AuthorizationContext($user, 'web', $north, new TenantMembership($north, 1, ['administrator'])))->fingerprint(),
    ];

    expect(array_unique($fingerprints))->toHaveCount(6);
});

it('grants tenant permissions exactly or by explicit wildcard', function (): void {
    $membership = new TenantMembership(new Tenant('institution', 1), 1, ['administrator'], ['members.*', 'subjects.view']);

    expect($membership->grants('members.invite'))->toBeTrue()
        ->and($membership->grants('subjects.view'))->toBeTrue()
        ->and($membership->grants('subjects.update'))->toBeFalse()
        ->and($membership->grants('members'))->toBeFalse()
        ->and($membership->hasRole('administrator'))->toBeTrue()
        ->and((new Tenant('institution', 1))->is(new Tenant('institution', '1')))->toBeTrue()
        ->and((new Tenant('institution', 1))->is(new Tenant('school', 1)))->toBeFalse()
        ->and(fn () => new Tenant('Institution!', 1))->toThrow(InvalidPermissionException::class);
});
