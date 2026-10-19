<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Data
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-data-config".
|
*/

return [

    'pagination' => [
        // Page size of sources without their own Paginate size.
        'default_per_page' => 25,

        // Hard upper limit for every source and every request (?per_page=).
        'max_per_page' => 100,
    ],

    'search' => [
        // Longer search input is rejected with 400 Bad Request.
        'max_length' => 200,

        // Whitespace-separated terms; each must match one of the searchable fields.
        'max_terms' => 8,
    ],

    'filters' => [
        // Maximum number of values in one in / not_in filter.
        'max_values' => 100,
    ],

    'sorting' => [
        // Maximum number of requested sort fields (?sort=name,-created_at).
        'max_fields' => 3,

        // Append the source's unique key as the last ordering, so pages never overlap or skip rows.
        'stable' => true,
    ],

    'queries' => [
        // Reject unknown filter, sort and include keys (400) instead of ignoring them.
        'strict' => true,
    ],

    'authorization' => [
        // Gate ability checked for query-builder and collection sources. Eloquent sources use the
        // model's policy (viewAny for listing, view for one record). Undefined abilities deny.
        'ability' => 'gcego-data.query',
    ],

];
