<?php

declare(strict_types=1);

use Gcego\Core\Exceptions\GcegoException;
use Gcego\Core\Exceptions\InvalidLifecycleException;
use Gcego\Core\Lifecycle\FrameworkState;
use Gcego\Core\Lifecycle\LifecycleManager;

it('starts uninitialized', function (): void {
    $lifecycle = new LifecycleManager;

    expect($lifecycle->state())->toBe(FrameworkState::Uninitialized)
        ->and($lifecycle->isBooted())->toBeFalse()
        ->and($lifecycle->failure())->toBeNull();
});

it('walks the happy path', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->beginBoot();

    expect($lifecycle->is(FrameworkState::Booting))->toBeTrue();

    $lifecycle->completeBoot();

    expect($lifecycle->state())->toBe(FrameworkState::Booted)
        ->and($lifecycle->isBooted())->toBeTrue();
});

it('makes registration idempotent', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->markRegistered();

    expect($lifecycle->state())->toBe(FrameworkState::Registered);

    $lifecycle->beginBoot();
    $lifecycle->completeBoot();
    $lifecycle->markRegistered();

    expect($lifecycle->state())->toBe(FrameworkState::Booted);
});

it('rejects booting before registration', function (): void {
    (new LifecycleManager)->beginBoot();
})->throws(InvalidLifecycleException::class, 'from "uninitialized" to "booting". Register Gcego\Core\GcegoServiceProvider first.');

it('rejects re-entrant boots', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->beginBoot();
    $lifecycle->beginBoot();
})->throws(InvalidLifecycleException::class, 'do not call boot() from a package or extension boot hook');

it('rejects every transition out of booted', function (FrameworkState $next): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->beginBoot();
    $lifecycle->completeBoot();

    expect(fn () => $lifecycle->transitionTo($next))->toThrow(InvalidLifecycleException::class);
})->with(FrameworkState::cases());

it('records failures and allows a retry', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->beginBoot();
    $failure = new RuntimeException('boom');
    $lifecycle->fail($failure);

    expect($lifecycle->state())->toBe(FrameworkState::Failed)
        ->and($lifecycle->failure())->toBe($failure);

    $lifecycle->beginBoot();

    expect($lifecycle->failure())->toBeNull();

    $lifecycle->completeBoot();

    expect($lifecycle->isBooted())->toBeTrue();
});

it('can cancel a boot', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->beginBoot();
    $lifecycle->cancelBoot();

    expect($lifecycle->state())->toBe(FrameworkState::Registered);
});

it('cannot complete a boot that never started', function (): void {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $lifecycle->completeBoot();
})->throws(InvalidLifecycleException::class);

it('declares the allowed transition table', function (): void {
    expect(FrameworkState::Uninitialized->canTransitionTo(FrameworkState::Registered))->toBeTrue()
        ->and(FrameworkState::Registered->canTransitionTo(FrameworkState::Booted))->toBeFalse()
        ->and(FrameworkState::Failed->canTransitionTo(FrameworkState::Booting))->toBeTrue()
        ->and(FrameworkState::Booting->canTransitionTo(FrameworkState::Failed))->toBeTrue();
});

it('throws exceptions implementing the package marker', function (): void {
    expect(InvalidLifecycleException::transition(FrameworkState::Booted, FrameworkState::Booting))->toBeInstanceOf(GcegoException::class);
});
