<?php

declare(strict_types=1);

use Gcego\Studio\Bootstrap\StudioBootstrapper;
use Gcego\Studio\Diagnostics\StudioInspector;
use Gcego\Studio\Health\StudioHealthManager;

it('adds little to booting the framework', function (): void {
    $start = hrtime(true);

    foreach (range(1, 20) as $ignored) {
        $this->refreshApplication();
    }

    $withStudio = (hrtime(true) - $start) / 1e6 / 20;

    expect(app(StudioBootstrapper::class)->isApplied())->toBeTrue()
        ->and(app()->resolved(StudioHealthManager::class))->toBeFalse()
        ->and(app()->resolved(StudioInspector::class))->toBeFalse()
        ->and($withStudio)->toBeLessThan(500);

    $verify = hrtime(true);
    app(StudioBootstrapper::class)->verify(true);
    expect((hrtime(true) - $verify) / 1e6)->toBeLessThan(100);
});
