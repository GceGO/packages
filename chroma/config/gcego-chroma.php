<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Chroma (styling)
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-chroma-config".
| Classes listed here are developer-authored: Tailwind scans this file in
| a normal Laravel app, so write every class out in full.
|
| Colors, color themes, light/dark appearance and accents live in
| config/gcego-swatches.php (gcego/swatches).
|
*/

return [

    // The default theme; null follows gcego-swatches.theme (recommended).
    'theme' => env('GCEGO_STYLING_THEME'),

    // Styling overrides for a theme (by the same name as its gcego/swatches color theme):
    // non-color tokens (light and dark) and preset overrides.
    // 'ocean' => [
    //     'tokens' => ['radius' => '1rem', 'radius-lg' => '1.25rem'],
    //     'presets' => ['surface.card' => ['rounded-3xl']],
    // ],
    'themes' => [],

    // Application-wide slot overrides by component class or interface:
    // Gcego\Blueprints\Contracts\BlueprintInterface::class => [
    //     'root' => ['gap-8'],
    //     'title' => ['mode' => 'replace', 'classes' => ['text-3xl', 'font-bold']],
    // ],
    'overrides' => [],

    // Throw when a renderer or Tailwind configuration uses a slot the component does not declare.
    'strict' => true,

];
