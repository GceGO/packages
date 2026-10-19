<?php

declare(strict_types=1);

return [
    'pagination' => [
        // Rows per page when neither the table nor the user chose one.
        'default_per_page' => 25,
        // Hard cap for every table and request (gcego/data applies its own cap too).
        'max_per_page' => 100,
        // Page sizes offered to users (each at most max_per_page).
        'options' => [10, 25, 50, 100],
    ],

    'search' => [
        'debounce_ms' => 350,
        'max_length' => 100,
    ],

    'selection' => [
        // Most rows one bulk action can receive; selections are re-checked server-side.
        'max_records' => 500,
    ],

    'preferences' => [
        'enabled' => true,
    ],

    'saved_views' => [
        'enabled' => true,
        'max_per_table' => 25,
    ],

    'storage' => [
        'connection' => null,
        'preferences_table' => 'gcego_table_preferences',
        'views_table' => 'gcego_table_views',
    ],

    'dates' => [
        // Display zone for Date and DateTime columns and date filters; null uses app.timezone.
        'timezone' => null,
    ],

    'export' => [
        // Rows fetched per query while exporting (gcego/data caps queries at its max_per_page).
        'chunk' => 100,

        // The most rows one "Export CSV" download writes (tables with Table::Exportable).
        'max_rows' => 10000,
    ],
];
