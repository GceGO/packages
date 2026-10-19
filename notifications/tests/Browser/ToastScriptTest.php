<?php

declare(strict_types=1);

/**
 * The toast region's JavaScript, exactly as rendered, run in Node (no browser
 * or Alpine needed: the component is a plain object).
 */
it('behaves as documented in the browser', function (): void {
    if (trim((string) shell_exec('command -v node')) === '') {
        $this->markTestSkipped('Node.js is not installed.');
    }

    $world = world();
    $html = (string) $this->actingAs($world['admin'])->get('/toasted')->getContent();
    preg_match('/<script data-gcego-toasts>(.*?)<\/script>/s', $html, $match);
    $script = tempnam(sys_get_temp_dir(), 'gcego-toasts').'.js';
    file_put_contents($script, $match[1] ?? '');

    $output = shell_exec('node '.escapeshellarg(__DIR__.'/toasts-harness.mjs').' '.escapeshellarg($script).' 2>&1');
    @unlink($script);
    $results = json_decode((string) $output, true);

    expect($results)->toBeArray()
        ->and($results['subscriptions'])->toBe(['gcego.notifications.abc'])
        ->and($results['afterDuplicate'])->toBe(['First', 'Second'])
        ->and($results['sanitizedType'])->toBe('info')
        ->and($results['links'])->toBe(['Good', 'Origin'])
        ->and($results['maxVisible'])->toBe(['b', 'c'])
        ->and($results['afterDismiss'])->toBe(['c'])
        ->and($results['fromAction'])->toBe([['Third', 'info'], ['Could not save', 'error']])
        ->and($results['received'])->toBe(['n1'])
        ->and($results['broadcast'])->toBe(['action-1-'.substr($results['broadcast'][0], 9), 'n1'])
        ->and($results['critical'])->toBe(['duration' => 0, 'assertive' => true])
        ->and($results['pausedKept'])->toBeTrue()
        ->and($results['expired'])->toBeTrue()
        ->and($results['icons'])->toBe(['lucide:bell', 'status.error', 'status.info'])
        ->and($results['iconSelector'])->toBe('template[data-gcego-toast-icon]')
        ->and($results['actionIcon'])->toBe('status.error')
        ->and($results['defaultIcon'])->toBe('status.warning');
})->group('browser');
