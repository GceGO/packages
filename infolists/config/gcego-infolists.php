<?php

declare(strict_types=1);

return [
    'formatting' => [
        // Shown for empty values.
        'empty_placeholder' => '—',
        // PHP date formats; null uses the locale's medium format ("Mar 1, 2026", "Mar 1, 2026 10:30 AM").
        'date_format' => null,
        'datetime_format' => null,
        // Display zone for DateTime entries; null uses app.timezone.
        'timezone' => null,
    ],

    'layouts' => [
        // Columns of entry grids when an infolist or section sets none (1 to 4).
        'default_columns' => 2,
    ],

    'lists' => [
        // Most items shown by ListEntry, KeyValue and RepeatableEntry.
        'max_items' => 100,
    ],

    'media' => [
        // Lifetime of temporary URLs for images on private disks.
        'temporary_url_minutes' => 5,
    ],
];
