<?php

declare(strict_types=1);

use Gcego\Studio\Tests\Fixtures\Extensions\ReportsExtension;

it('reports what was assembled', function (): void {
    $report = studio()->inspect();

    expect(array_keys($report))->toContain('studio', 'bootstrap', 'packages', 'compatibility', 'appearance', 'panels', 'modules', 'pages', 'navigation', 'definitions', 'assets', 'extensions')
        ->and($report['studio']['version'])->toBe('0.1.0')
        ->and($report['bootstrap']['ok'])->toBeTrue()
        ->and(collect($report['modules'])->pluck('module')->all())->toBe(['academics'])
        ->and($report['definitions'])->toMatchArray(['panel' => 2, 'page' => 6]);
});

it('prints a summary and JSON', function (): void {
    $this->reboot(['gcego-studio.extensions' => [ReportsExtension::class]]);

    $this->artisan('gcego:studio:inspect')
        ->expectsOutputToContain('GCEGO Admin Studio 0.1.0')
        ->expectsOutputToContain('panel:admin')
        ->expectsOutputToContain('acme/reports')
        ->assertSuccessful();

    $this->artisan('gcego:studio:inspect', ['--json' => true])->expectsOutputToContain('"panels"')->assertSuccessful();
});
