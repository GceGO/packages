<?php

declare(strict_types=1);

return [
    'charts' => [
        // The chart adapter used by Chart widgets (Chart.js, loaded lazily by resources/js/charts).
        'default_adapter' => 'chartjs',
        // Most labels (points) and datasets one chart accepts; larger data is refused, not truncated silently.
        'max_points' => 500,
        'max_datasets' => 10,
    ],

    'refresh' => [
        // Widgets never poll unless they declare new Refresh(every: …); this is the shortest allowed interval.
        'minimum_interval_seconds' => 30,
    ],

    'cache' => [
        // Widget data is cached only for widgets with a Cache node, and only when this is true.
        'enabled' => false,
        'store' => null,
        'ttl' => 300,
    ],

    'activity' => [
        'default_limit' => 10,
        'max_limit' => 50,
    ],

    'dates' => [
        // Zone of date-range boundaries (today, this month…); null uses app.timezone.
        'timezone' => null,
        'max_range_days' => 732,
    ],

    'formatting' => [
        'currency' => 'XAF',
    ],
];
