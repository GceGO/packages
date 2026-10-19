<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Analytics
|--------------------------------------------------------------------------
|
| Datasets, metrics, reports and dashboards are declared in PHP under
| app/GCEGo/Analytics/{Datasets,Metrics,Reports,Dashboards} and discovered
| by gcego/registry. Every query is authorized and tenant-scoped before it
| aggregates; limits are enforced on the server. No option here disables
| authorization or tenant isolation.
|
*/

return [

    'enabled' => true,

    'discovery' => [
        'enabled' => true,
        // Each path holds Datasets/, Metrics/, Reports/ and Dashboards/ folders.
        'paths' => [app_path('GCEGo/Analytics')],
    ],

    // The time zone of periods and series when the viewer has none (null: app.timezone).
    'timezone' => null,

    'queries' => [
        'max_rows' => 10000,
        'max_chart_points' => 1000,
        'max_group_cardinality' => 500,
        // The longest period a query may cover.
        'max_range_days' => 1830,
        'max_page_size' => 200,
        // Statement timeout in milliseconds where the database supports it (MySQL, PostgreSQL); null: none.
        'timeout_ms' => null,
    ],

    'privacy' => [
        // Groups with fewer rows than this are hidden unless a dataset sets its own value (0: off).
        'minimum_group_size' => 0,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 900,
        // A Laravel cache store (null: the default store).
        'store' => null,
    ],

    'exports' => [
        'enabled' => true,
        'queue' => 'analytics',
        'connection' => null,
        'disk' => 'local',
        'directory' => 'gcego-analytics/exports',
        'max_rows' => 100000,
        // Exports up to this many rows are generated in the request; larger ones are queued.
        'sync_rows' => 2000,
        'chunk' => 1000,
        // Hours a finished export can be downloaded.
        'ttl_hours' => 24,
        // Running exports per user.
        'max_concurrent' => 3,
    ],

    'scheduled_reports' => [
        'enabled' => true,
        // Register "gcego:analytics:run-schedules" (every five minutes) and "gcego:analytics:prune-exports" (hourly) with Laravel's scheduler.
        'schedule' => true,
        'batch' => 50,
    ],

    'security' => [
        // Definitions without gcego/security requirements are denied (deny by default).
        'require_authorization' => true,
        'enforce_tenant_scope' => true,
    ],

    'observability' => [
        'log_channel' => null,
    ],

    'integrations' => [
        // Register the workflow datasets and metrics (Integration\Workflows) when gcego/workflows is installed.
        'workflows' => true,
    ],

    'panels' => [
        // Add "<panel>/analytics/{dashboard}" and "<panel>/reports/{report}" to every gcego/panels panel.
        'enabled' => true,
    ],

];
