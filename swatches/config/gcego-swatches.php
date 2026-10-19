<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Swatches
|--------------------------------------------------------------------------
|
| Colors, themes and appearance for GCEGO Admin Studio.
| Publish with "php artisan vendor:publish --tag=gcego-swatches-config".
|
*/

return [

    // The default theme: "gcego" or a preset (midnight, slate, ocean, forest) or one of "themes" below.
    'theme' => env('GCEGO_THEME', env('GCEGO_STYLING_THEME', 'gcego')),

    // Application themes. Values are hex, rgb(), hsl(), oklch(), oklab(), CSS color names or
    // "{token}" references. Tokens left out are inherited from "extends" (default "gcego").
    // 'campus' => [
    //     'label' => 'Campus',
    //     'extends' => 'ocean',
    //     'light' => ['primary' => '#1d4ed8', 'primary-hover' => '#1e40af', 'primary-soft' => '#e8effd'],
    //     'dark' => ['primary' => '#60a5fa', 'primary-hover' => '#93c5fd', 'primary-foreground' => '#06142e'],
    // ],
    'themes' => [],

    // Application palettes, available as Tailwind utilities once "php artisan gcego:swatches:generate"
    // has written them to CSS (bg-brand-600 …). A single color generates an 11-step scale.
    // 'brand' => '#2563eb',
    // 'sand' => [50 => '#fbf8f1', 100 => '#f4ecdb', ... 950 => '#2a2111'],
    'palettes' => [],

    'appearance' => [
        // "light", "dark" or "system" (follow the operating system).
        'default' => env('GCEGO_APPEARANCE', env('GCEGO_STYLING_APPEARANCE', 'system')),

        // Let users switch with window.GcegoAppearance.set('dark'); stored in localStorage only.
        'allow_switching' => true,

        'storage_key' => 'gcego.appearance',
    ],

    // Main colors users may pick for themselves: window.GcegoAppearance.setAccent('red').
    // Stored in localStorage only, like the appearance.
    'accents' => [
        'enabled' => true,
        // The presets to offer, in order (teal, blue, indigo, violet, rose, red, orange, amber, green); null for all.
        'only' => null,
        'storage_key' => 'gcego.accent',
    ],

    'css' => [
        // true when your stylesheet imports resources/css/themes.css (all presets, about 1.9 KB);
        // false (default) prints only the active preset at runtime, in <x-gcego-swatches::theme>.
        'static_presets' => (bool) env('GCEGO_SWATCHES_STATIC_PRESETS', false),
    ],

    'validation' => [
        // Refuse to boot when a configured theme fails WCAG 2.2 AA contrast (structural errors always fail).
        'enforce_contrast' => (bool) env('GCEGO_SWATCHES_ENFORCE_CONTRAST', false),

        // Advisory warnings in gcego:swatches:validate (never refuse a theme): APCA lightness
        // contrast (proposed for WCAG 3) and simulated color-vision deficiencies.
        'apca' => (bool) env('GCEGO_SWATCHES_APCA', false),
        'color_vision' => (bool) env('GCEGO_SWATCHES_COLOR_VISION', false),
    ],

    // gcego:swatches:cache writes compiled runtime theme CSS here (also run by "php artisan optimize").
    'cache' => [
        'path' => env('GCEGO_SWATCHES_CACHE', 'bootstrap/cache/gcego-swatches.php'),
    ],

    // A developer tool, off by default. When enabled it is still guarded by the
    // "viewSwatchesExplorer" gate (allowed only in the local environment unless the app defines it).
    'explorer' => [
        'enabled' => (bool) env('GCEGO_SWATCHES_EXPLORER', false),
        'path' => 'gcego/colors',
        'middleware' => ['web'],
    ],

];
