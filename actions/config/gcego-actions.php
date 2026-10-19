<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Actions
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-actions-config".
|
*/

return [

    // Confirmation defaults by action type; an action's Confirmation property overrides them.
    'confirmation' => [
        'delete' => true,
        'force_delete' => true,
        'restore' => false,
        'bulk' => true,

        // Seconds a confirmation token stays valid. Each token works once.
        'ttl' => 600,
    ],

    'transactions' => [
        // Run actions in a database transaction unless the action says otherwise.
        'enabled' => true,
    ],

    'bulk' => [
        // Selections larger than this are rejected.
        'max_selection' => 500,

        // Records resolved and authorized per query (at most 100).
        'chunk_size' => 100,
    ],

    'idempotency' => [
        // Seconds an idempotency key of an Idempotent action is remembered.
        'ttl' => 3600,
    ],

    'cache' => [
        // Store for single-use confirmation tokens and idempotency keys. Use a store with atomic
        // add() shared by all servers (redis, database, memcached) in production. Null: the default store.
        'store' => null,
    ],

];
