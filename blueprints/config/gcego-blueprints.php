<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Blueprints
|--------------------------------------------------------------------------
|
| Blueprints are gcego/registry definitions of kind "blueprint". This package
| owns that kind and adds its discovery roots; the "module" kind belongs to
| gcego/modules, and both can share app/GCEGo/Modules.
|
| Publish with "php artisan vendor:publish --tag=gcego-blueprints-config".
|
*/

return [

    'discovery' => [
        // Add the paths below to gcego-registry's discovery roots (kind "blueprint").
        'enabled' => env('GCEGO_BLUEPRINTS_DISCOVERY', true),

        // Folders searched recursively for blueprint modules.
        'paths' => [
            app_path('GCEGo/Modules'),
        ],

        // Entrypoints: a file named after its folder (Academics/Subjects/Subjects.php).
        // Module.php files (gcego/modules) never match, so both packages can share a folder.
        'entrypoint' => '#(?:^|/)(?!Module/)([^/]+)/\1\.php$#',
    ],

    // Check class references when the registry registers or resolves a
    // blueprint: every referenced class must be autoloadable, plus the rules
    // installed feature packages add. Can only be turned off in the "local"
    // and "testing" environments; everywhere else it is always on.
    'validate_references' => env('GCEGO_BLUEPRINTS_VALIDATE_REFERENCES', true),

];
