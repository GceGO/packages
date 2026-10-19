<?php

declare(strict_types=1);
use Gcego\Luminaries\Presets\IconPresets;

return [

    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | Bare names ("pencil-square") are looked up in inline icons first, then in
    | this provider. Heroicons outline keeps existing GCEGO icon names working.
    |
    */

    'default_provider' => 'heroicon',

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Blade Icons sets exposed as canonical namespaces ("lucide:pencil").
    | "set" is the Blade Icons set name; "variant" is the file prefix inside
    | the set (Heroicons: o, s, m, c). "stroke" marks stroke-drawn icons, the
    | only ones a stroke width applies to.
    |
    */

    'providers' => [
        'heroicon' => ['enabled' => true, 'set' => 'heroicons', 'variant' => 'o', 'label' => 'Heroicons (outline)', 'style' => 'outline', 'stroke' => true],
        'heroicon-solid' => ['enabled' => true, 'set' => 'heroicons', 'variant' => 's', 'label' => 'Heroicons (solid)', 'style' => 'solid', 'stroke' => false],
        'heroicon-mini' => ['enabled' => true, 'set' => 'heroicons', 'variant' => 'm', 'label' => 'Heroicons (mini, 20px)', 'style' => 'mini', 'stroke' => false],
        'heroicon-micro' => ['enabled' => true, 'set' => 'heroicons', 'variant' => 'c', 'label' => 'Heroicons (micro, 16px)', 'style' => 'micro', 'stroke' => false],
        'lucide' => ['enabled' => true, 'set' => 'lucide', 'variant' => null, 'label' => 'Lucide', 'style' => 'outline', 'stroke' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom SVG collections
    |--------------------------------------------------------------------------
    |
    | Namespace => directory of .svg files ("custom:gcego-logo" reads
    | gcego-logo.svg). Directories must be inside a trusted root and every file
    | is validated before it is rendered. Example:
    |
    |     'custom' => ['path' => resource_path('icons'), 'label' => 'Application icons'],
    |
    */

    'collections' => [],

    /*
    |--------------------------------------------------------------------------
    | Semantic aliases
    |--------------------------------------------------------------------------
    |
    | Presets are applied in order, then "aliases" (which win). Available
    | presets: framework, education. Example override:
    |
    |     'actions.edit' => 'lucide:pencil',
    |
    */

    'presets' => ['framework'],

    'aliases' => [],

    /*
    |--------------------------------------------------------------------------
    | Sizes and tones
    |--------------------------------------------------------------------------
    |
    | Size tokens map to Tailwind classes; tones map to gcego/chroma color
    | utilities. Icons use currentColor, so they inherit text color (and dark
    | mode) unless a tone is given. To customize, replace the arrays, e.g.
    | 'sizes' => [...IconPresets::Sizes, '3xl' => 'size-12'], and make sure
    | Tailwind scans your config file.
    |
    */

    'default_size' => 'md',

    'sizes' => IconPresets::Sizes,

    'tones' => IconPresets::Tones,

    /*
    | Classes added to every icon (e.g. "shrink-0").
    */

    'class' => IconPresets::Icon,

    /*
    |--------------------------------------------------------------------------
    | Missing icons
    |--------------------------------------------------------------------------
    |
    | exception:   throw IconNotFoundException with diagnostics.
    | fallback:    log a warning and render the fallback icon (keeps any label).
    | placeholder: render a neutral dashed box naming the icon (development).
    | empty:       render nothing.
    |
    */

    'missing' => [
        'behavior' => env('LUMINARIES_MISSING_ICON', (bool) env('APP_DEBUG', false) ? 'exception' : 'fallback'),
        'fallback' => 'heroicon:question-mark-circle',
        'log' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Custom collection directories must resolve inside one of these roots.
    |
    */

    'security' => [
        'trusted_roots' => [resource_path()],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | gcego:icons:cache writes the icon manifest here (also run by
    | `php artisan optimize`). It holds package metadata only.
    |
    */

    'cache' => [
        'path' => base_path('bootstrap/cache/gcego-luminaries.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade Icons components
    |--------------------------------------------------------------------------
    |
    | Blade Icons registers one Blade component per icon (thousands) on every
    | request unless its manifest is cached. Luminaries replaces them with
    | <x-gcego-icon>, so they are disabled unless set to true here.
    |
    */

    'blade_icons_components' => false,

    /*
    |--------------------------------------------------------------------------
    | Icon explorer
    |--------------------------------------------------------------------------
    |
    | A developer tool, off by default. When enabled it is still guarded by
    | the "viewLuminariesExplorer" gate (allowed only in the local
    | environment unless the application defines the gate).
    |
    */

    'explorer' => [
        'enabled' => (bool) env('LUMINARIES_EXPLORER', false),
        'path' => 'gcego/icons',
        'middleware' => ['web'],
        'per_page' => 120,
    ],

];
