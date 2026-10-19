<?php

declare(strict_types=1);

use Gcego\Studio\Contracts\StudioExtension;
use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Extensions\StudioExtensionRegistry;
use Gcego\Studio\Health\HealthStatus;

/**
 * Runs a README example as written.
 */
function readmeExample(string $name): mixed
{
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    preg_match('/<!-- example:'.$name.' -->\s*```php\n(.*?)```/s', $readme, $match);
    $code = (string) preg_replace('/^use [^;]+;\n/m', '', $match[1] ?? 'throw new RuntimeException("missing example");');

    return eval('use Gcego\Studio\Contracts\StudioExtension; use Gcego\Studio\Extensions\StudioExtensionContext; use Gcego\Studio\Contracts\StudioHealthCheck; use Gcego\Studio\Health\HealthCheckResult; '.$code);
}

it('runs the documented entry point', function (): void {
    expect(readmeExample('studio'))->toBe([['admin', 'school'], ['academics'], true, 'pass', true]);
});

it('runs the documented extension and health check', function (): void {
    $extension = readmeExample('extension');
    $check = readmeExample('health-check');
    $registry = new StudioExtensionRegistry(app());
    $registry->add($extension);

    expect($extension)->toBeInstanceOf(StudioExtension::class)
        ->and(($registry->contexts()['acme/reports']->sections()['reports'])())->toBe(['enabled' => true])
        ->and($check)->toBeInstanceOf(StudioHealthCheck::class)
        ->and($check->run()->status)->toBe(HealthStatus::Pass);
});
