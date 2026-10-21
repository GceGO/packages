<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Core\Diagnostics\DiagnosticStatus;
use Gcego\Core\Diagnostics\EnvironmentChecker;
use Illuminate\Contracts\Foundation\Application;

it('passes on the running environment', function (): void {
    $results = (new EnvironmentChecker(app()))->check();

    expect(array_map(fn (DiagnosticResult $result): string => $result->check, $results))->toBe(['php.version', 'php.extensions', 'laravel.version'])
        ->and(array_map(fn (DiagnosticResult $result): DiagnosticStatus => $result->status, $results))->each->toBe(DiagnosticStatus::Passed)
        ->and((new EnvironmentChecker(app()))->failures())->toBe([]);
});

it('fails on an old PHP version', function (): void {
    $failures = (new EnvironmentChecker(app(), phpVersion: '8.2.10'))->failures();

    expect($failures)->toHaveCount(1)
        ->and($failures[0]->message)->toBe('PHP 8.2.10 is older than the required 8.3.0.')
        ->and($failures[0]->details)->toBe(['version' => '8.2.10', 'minimum' => '8.3.0']);
});

it('fails on missing PHP extensions', function (): void {
    $failures = (new EnvironmentChecker(app(), loadedExtensions: ['Core', 'ctype', 'filter', 'hash', 'session', 'tokenizer']))->failures();

    expect($failures[0]->check)->toBe('php.extensions')
        ->and($failures[0]->message)->toBe('Missing PHP extension(s): mbstring, openssl.');
});

it('builds structured reports', function (): void {
    $report = new DiagnosticReport(
        DiagnosticResult::passed('a', 'ok'),
        DiagnosticResult::warning('b', 'careful', ['count' => 2]),
        DiagnosticResult::failed('c', 'broken'),
    );

    expect($report->count())->toBe(3)
        ->and($report->hasFailures())->toBeTrue()
        ->and($report->hasWarnings())->toBeTrue()
        ->and($report->isHealthy())->toBeFalse()
        ->and($report->failures()[0]->check)->toBe('c')
        ->and($report->warnings()[0]->check)->toBe('b')
        ->and($report->result('b')?->details)->toBe(['count' => 2])
        ->and($report->result('missing'))->toBeNull()
        ->and(iterator_to_array($report))->toBe($report->results())
        ->and($report->toArray()[1])->toMatchArray(['check' => 'b', 'code' => 'b', 'status' => 'warning', 'severity' => 'warning', 'message' => 'careful', 'details' => ['count' => 2]])
        ->and((new DiagnosticReport(DiagnosticResult::passed('a', 'ok')))->isHealthy())->toBeTrue();
});

it('rejects PHP and Laravel versions outside their supported major ranges', function (): void {
    expect((new EnvironmentChecker(app(), phpVersion: '9.0.0'))->failures()[0]->check)->toBe('php.version');
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('version')->andReturn('14.0.0');
    expect((new EnvironmentChecker($application))->failures()[0]->check)->toBe('laravel.version');
});
