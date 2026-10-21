<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticRegistry;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Core\Gcego;
use Gcego\Core\Tests\Fixtures\FixtureDiagnosticCheck;

it('runs custom checks in deterministic order through the public API and CLI', function (): void {
    $registry = app(DiagnosticRegistry::class);
    $check = new FixtureDiagnosticCheck('acme.a', fn () => DiagnosticResult::warning('acme.a', 'Needs attention.'));
    $registry->register(new FixtureDiagnosticCheck('acme.z', fn () => DiagnosticResult::passed('acme.z', 'Ready.')));
    $registry->register($check);
    $registry->register($check);
    $report = app(Gcego::class)->diagnose();

    expect(array_slice(array_column($report->toArray(), 'check'), -2))->toBe(['acme.a', 'acme.z'])
        ->and($report->result('acme.a')?->status)->toBe(DiagnosticStatus::Warning);
    $this->artisan('gcego:diagnose', ['--strict' => true])->assertFailed();
});

it('isolates crashing and mismatched checks without exposing exception messages', function (): void {
    $registry = app(DiagnosticRegistry::class);
    $registry->register(new FixtureDiagnosticCheck('acme.broken', function (): never {
        throw new RuntimeException('password=secret');
    }));
    $registry->register(new FixtureDiagnosticCheck('acme.mismatch', fn () => DiagnosticResult::passed('framework.boot', 'Wrong id.')));
    $registry->register(new FixtureDiagnosticCheck('acme.ready', fn () => DiagnosticResult::passed('acme.ready', 'Ready.')));
    $report = app(Gcego::class)->diagnose();

    expect($report->result('acme.broken')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('acme.mismatch')?->status)->toBe(DiagnosticStatus::Failed)
        ->and($report->result('acme.ready')?->status)->toBe(DiagnosticStatus::Passed)
        ->and(json_encode($report->toArray()))->not->toContain('password=secret');
});

it('rejects reserved, invalid and duplicate check ids', function (): void {
    $registry = new DiagnosticRegistry;
    foreach (['framework.boot', 'diagnostics.custom', 'INVALID!'] as $id) {
        expect(fn () => $registry->register(new FixtureDiagnosticCheck($id, fn () => DiagnosticResult::passed($id, 'ok'))))->toThrow(LogicException::class);
    }
    $registry->register(new FixtureDiagnosticCheck('acme.a', fn () => DiagnosticResult::passed('acme.a', 'ok')));
    expect(fn () => $registry->register(new FixtureDiagnosticCheck('acme.a', fn () => DiagnosticResult::passed('acme.a', 'ok'))))->toThrow(LogicException::class);
});

it('rejects recursive execution and registration during checks and recovers for later runs', function (): void {
    $registry = new DiagnosticRegistry;
    $registry->register(new FixtureDiagnosticCheck('acme.recursive', function () use ($registry): DiagnosticResult {
        $registry->run();

        return DiagnosticResult::passed('acme.recursive', 'unreachable');
    }));
    $registry->register(new FixtureDiagnosticCheck('acme.register', function () use ($registry): DiagnosticResult {
        $registry->register(new FixtureDiagnosticCheck('acme.late', fn () => DiagnosticResult::passed('acme.late', 'ok')));

        return DiagnosticResult::passed('acme.register', 'unreachable');
    }));

    expect(array_column(array_map(fn ($result) => $result->toArray(), $registry->run()), 'status'))->toBe(['failed', 'failed']);
    $registry->register(new FixtureDiagnosticCheck('acme.recovery', fn () => DiagnosticResult::passed('acme.recovery', 'ok')));
    expect($registry->run())->toHaveCount(3);
});
