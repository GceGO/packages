<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO CLI
|--------------------------------------------------------------------------
|
| Settings of the gcego:* developer commands. Generators write where the
| packages discover definitions (gcego-registry.discovery.roots and each
| package's discovery paths); a path here overrides that for one kind.
| Publish with "php artisan vendor:publish --tag=gcego-cli-config".
|
*/

return [

    'enabled' => true,

    // Target folders per definition kind; null = the folder the package discovers.
    // Keys: blueprint, module, page, panel, layout, navigation, widget, workflow,
    // settings, settings-page, analytics, notification, support, test.
    'paths' => [
        // Classes generated definitions point to (action handlers, widget data
        // providers). Keep it outside every discovery folder.
        'support' => app_path('GCEGo/Support'),
        'notification' => app_path('Notifications'),
        'test' => base_path('tests/Feature'),
    ],

    'generators' => [
        // Also generate a test (gcego/testing) for each definition (same as --test).
        'generate_tests' => false,
        // Trusted custom generators (Gcego\Cli\Contracts\Generator classes), run with gcego:make <id>.
        'custom' => [],
    ],

    'stubs' => [
        // Application overrides: <custom_path>/<stub>.stub ("php artisan vendor:publish --tag=gcego-cli-stubs").
        'custom_path' => base_path('stubs/gcego'),
    ],

    'inspection' => [
        // Trusted custom inspectors (Gcego\Cli\Contracts\DefinitionInspector classes).
        'inspectors' => [],
        // Most rows a listing prints at once (use --limit / --offset to page).
        'limit' => 200,
    ],

    'diagnostics' => [
        'enabled' => true,
        // Trusted custom checks (Gcego\Cli\Contracts\DiagnosticCheck classes), run by gcego:doctor.
        'checks' => [],
    ],

];
