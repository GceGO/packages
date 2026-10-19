<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Toolkit
    |--------------------------------------------------------------------------
    |
    | The toolkit registers its services only while this is true. It never
    | boots anything in a request; installing the package with
    | "composer require --dev" keeps it out of production entirely.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Assertion failures
    |--------------------------------------------------------------------------
    |
    | Failures list the subject, the expected and actual values, and a
    | suggestion. Values under keys that match a "redact" pattern are
    | replaced by "[redacted]" before anything is printed.
    |
    */

    'assertions' => [
        'verbose_failures' => true,
        'redact' => ['*password*', '*secret*', '*token*', '*api_key*', '*apikey*', '*authorization*', '*cookie*', '*credential*', '*private_key*', 'remember_token', 'two_factor_*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    |
    | Temporary workspaces live under this directory (the system temporary
    | directory by default). Every workspace is unique to the process and
    | the parallel-testing token, and is deleted by the state resetter.
    |
    | "users" and "tenants" name classes that implement
    | Gcego\Testing\Contracts\UserFixtureProvider and TenantFixtureProvider.
    |
    */

    'fixtures' => [
        'directory' => null,
        'users' => null,
        'tenants' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | "repeated_queries" is how many executions of one query shape the N+1
    | check reports by default. Duration limits are never applied unless a
    | test asserts one: CI hardware varies, so pick generous limits.
    |
    */

    'performance' => [
        'repeated_queries' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    |
    | Static checks read rendered markup and always run in PHP. Browser
    | checks run axe-core in Playwright's Chromium and need Node with the
    | "playwright" and "axe-core" packages. "node_modules" is found by
    | walking up from the base path when null. When the tools are missing
    | the audit throws instead of passing; skip with browserAvailable().
    |
    */

    'accessibility' => [
        'node' => 'node',
        'node_modules' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Isolation
    |--------------------------------------------------------------------------
    |
    | What TestStateResetter::reset() clears.
    |
    */

    'isolation' => [
        'reset_registry' => true,
        'reset_runtime' => true,
        'reset_tenant_context' => true,
    ],

];
