<?php

declare(strict_types=1);

use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Collect every object reachable from $root through properties, without entering the container.
 *
 * @return list<object>
 */
function reachableObjects(object $root): array
{
    $seen = [];
    $stack = [$root];

    while ($stack !== []) {
        $object = array_pop($stack);

        if (isset($seen[spl_object_id($object)]) || $object instanceof Container) {
            continue;
        }

        $seen[spl_object_id($object)] = $object;

        foreach ((array) $object as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_object($item)) {
                    $stack[] = $item;
                }
            }
        }
    }

    return array_values($seen);
}

it('keeps framework singletons free of request state across requests', function (): void {
    Route::get('/probe', fn () => response()->json(['booted' => app(Gcego::class)->isBooted()]));

    $before = [app(Gcego::class), app(PackageManager::class), app(ExtensionManager::class), app(LifecycleManager::class)];

    $this->get('/probe?user=alice')->assertOk()->assertJson(['booted' => true]);
    $this->get('/probe?user=bob')->assertOk()->assertJson(['booted' => true]);

    $after = [app(Gcego::class), app(PackageManager::class), app(ExtensionManager::class), app(LifecycleManager::class)];

    expect($after)->toBe($before);

    foreach ($after as $singleton) {
        foreach (reachableObjects($singleton) as $object) {
            expect($object)->not->toBeInstanceOf(Request::class);
        }
    }
});

it('does not boot twice across requests', function (): void {
    $result = app(Gcego::class)->bootResult();

    $this->get('/')->assertNotFound();
    $this->get('/')->assertNotFound();

    expect(app(Gcego::class)->bootResult())->toBe($result);
});
