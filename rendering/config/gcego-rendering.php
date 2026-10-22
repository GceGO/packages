<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Rendering
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-rendering-config".
|
*/

return [

    // Throw on the first rendering failure (true), or report it and show an
    // accessible "could not be displayed" placeholder for that component (false).
    // Defaults to true outside production, so one failing component never takes
    // a whole production page down.
    'strict' => env(
        'GCEGO_RENDERING_STRICT',
        env('APP_ENV', 'production') !== 'production'
    ),

    // Validate component definitions before rendering.
    'validate' => true,

    // Maximum component nesting depth rendered (guards runaway recursion).
    'max_depth' => 64,

    'authorization' => [
        // Gate ability checked by renderAuthorized(), <x-gcego-rendering::render>,
        // render operations and authorizedBlueprint(). Laravel denies undefined
        // abilities, so nothing renders publicly until you define it (fails closed):
        // Gate::define('gcego-rendering.render', fn (?User $user, string $identity) => ...);
        'ability' => 'gcego-rendering.render',
    ],

];
