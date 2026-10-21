<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Runtime\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Integration');

/**
 * Assert that $callback throws $class somewhere in its exception chain
 * (Livewire mounts inside Blade, so errors arrive wrapped in a ViewException).
 *
 * @param  class-string<Throwable>  $class
 */
function expectRootCause(Closure $callback, string $class, string $message = ''): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof $class) {
                expect($current->getMessage())->toContain($message);

                return;
            }
        }

        throw $exception;
    }

    test()->fail('Expected '.$class.' to be thrown.');
}

/**
 * Collapse whitespace between tags so assertions read naturally.
 */
function html(string $html): string
{
    return trim((string) preg_replace(['/>\s+</', '/\s+/'], ['><', ' '], $html));
}
