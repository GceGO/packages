<?php

declare(strict_types=1);

use Gcego\Testing\Accessibility\BrowserAccessibility;
use Gcego\Testing\Exceptions\TestEnvironmentException;
use Gcego\Testing\GcegoTest;

it('audits markup with axe-core in a real browser', function (): void {
    GcegoTest::accessibility('<h1>Subjects</h1><label for="q">Search</label><input id="q">')->assertAccessibleInBrowser();

    expect(failure(fn () => GcegoTest::accessibility('<h1>Subjects</h1><img src="x.png">')->assertAccessibleInBrowser()))->toContain('[image-alt]');
})->skip(fn (): bool => ! GcegoTest::manager()->browser()->available(), 'Node, Playwright or axe-core is not available.');

it('throws instead of passing when the browser tools are missing', function (): void {
    $browser = new BrowserAccessibility(node: 'definitely-not-node-'.getmypid());

    expect($browser->available())->toBeFalse()
        ->and($browser->unavailableReason())->toContain('Node is not available')
        ->and(fn () => $browser->auditHtml('<p>x</p>'))->toThrow(TestEnvironmentException::class);
});
