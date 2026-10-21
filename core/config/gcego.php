<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Studio
|--------------------------------------------------------------------------
|
| Framework-level settings for gcego/core. Publish this file with
| "php artisan vendor:publish --tag=gcego-config". env() is only read here,
| so the values survive "php artisan config:cache".
|
*/

return [

    // Turn the whole framework off without uninstalling it. When false, GCEGO
    // registers its services but never boots packages or extensions.
    'enabled' => env('GCEGO_STUDIO_ENABLED', true),

    // Display name of this GCEGO installation.
    'name' => env('GCEGO_STUDIO_NAME', 'GCEGO Studio'),

    // Boot automatically once Laravel has booted every service provider. Set
    // to false to call app(Gcego\Core\Gcego::class)->boot() yourself.
    'auto_boot' => env('GCEGO_STUDIO_AUTO_BOOT', true),

    // Run the cheap PHP/Laravel environment checks during boot and refuse to
    // boot when one fails. The checks never touch the network or filesystem.
    'environment_checks' => env('GCEGO_STUDIO_ENVIRONMENT_CHECKS', true),

    'packages' => [
        // Installed GCEGO packages that should not boot, by Composer name,
        // e.g. ['gcego/analytics'].
        'disabled' => [],
    ],

    'extensions' => [
        // Registered extensions that should not activate, e.g. ['tables.export'].
        'disabled' => [],
    ],

    'diagnostics' => [
        // Detailed per-step timing is opt-in. Status and failure records stay available.
        'profiling' => env('GCEGO_STUDIO_PROFILING', false),
        'slow_boot_ms' => 100.0,
        'slow_package_ms' => 50.0,
        'slow_extension_ms' => 50.0,
    ],

];
