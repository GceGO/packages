<?php

declare(strict_types=1);

use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Diagnostics\DiagnosticResult;

it('redacts credential-shaped messages and sensitive details in structured JSON', function (): void {
    $result = DiagnosticResult::failed('acme.connection', 'password=very-secret token=secret-token Bearer abc123 https://alice:pass@example.test', ['api_key' => 'private-value', 'items' => ['password=another-secret'], 'version' => '1.0.0'], resolution: 'Set password=resolution-secret');
    $json = json_encode(new DiagnosticReport($result), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('very-secret', 'secret-token', 'abc123', 'alice:pass', 'private-value', 'another-secret', 'resolution-secret')
        ->and($json)->toContain('[redacted]')
        ->and($result->details['version'])->toBe('1.0.0');
});

it('rejects arbitrary nested or object-valued detail payloads from custom checks', function (): void {
    $result = DiagnosticResult::passed('acme.metadata', 'Ready.', ['unknown' => ['nested' => ['value' => 'hidden-secret']], 'object' => (object) ['value' => 'hidden-secret']]);
    expect(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('hidden-secret');
});

it('maps overall health independently from compatibility isHealthy semantics', function (): void {
    expect((new DiagnosticReport)->health())->toBe('healthy')
        ->and((new DiagnosticReport(DiagnosticResult::warning('a', 'Warning')))->health())->toBe('warning')
        ->and((new DiagnosticReport(DiagnosticResult::warning('a', 'Warning')))->isHealthy())->toBeTrue()
        ->and((new DiagnosticReport(DiagnosticResult::failed('a', 'Error')))->health())->toBe('critical');
});

it('redacts JSON-shaped credentials and environment variable credentials', function (): void {
    $result = DiagnosticResult::warning('acme.credentials', '{"password":"json-secret"} DB_PASSWORD=env-secret', ['dbPassword' => 'camel-secret']);
    expect(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('json-secret', 'env-secret', 'camel-secret');
});

it('keeps custom diagnostic payloads JSON-compatible despite invalid UTF-8 and non-finite numbers', function (): void {
    $result = DiagnosticResult::passed('acme.encoding', "invalid \xFF", ['measurements' => [INF, NAN], 'scalar' => INF, 'text' => str_repeat('é', 3000)]);
    $encoded = json_encode(new DiagnosticReport($result), JSON_THROW_ON_ERROR);
    expect($encoded)->toBeString()
        ->and($encoded)->toContain('non-finite number');
});
