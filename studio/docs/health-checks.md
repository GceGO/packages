# Health checks

`Studio::health(bool $production = false): HealthReport`, or `php artisan gcego:studio:health [--production] [--json]`.

- Each `HealthCheckResult` has `id`, `label`, `status` (`pass`, `warning`, `failure`, `skipped`), `message`, `remediation`, `package` and `details`.
- `HealthReport::healthy()` is true when nothing failed (warnings allowed). The command exits with 1 otherwise.
- JSON shape: `{"healthy": bool, "summary": {pass, warning, failure, skipped}, "checks": [ … ]}`.

Checks are read-only and run in a fixed order: built-in, then production (with `--production`), then extension checks. A check that throws becomes a failure with the exception message, and the remaining checks still run.

## Production readiness

| Check | Failure or warning when |
| --- | --- |
| `production.config-cache` | configuration not cached (warning) |
| `production.route-cache` | routes not cached (warning) |
| `production.view-cache` | no compiled views (warning) |
| `production.debug` | `APP_DEBUG` on (failure) |
| `production.https` | `APP_URL` not HTTPS (warning) |
| `production.session` | the `array` driver; `http_only` off; `same_site` not `lax`/`strict`; `secure` off over HTTPS (warning) |
| `production.autoload` | Composer autoloader not optimized (warning) |
| `production.assets` | dev server running or no build (failure) |

## In your own monitoring

```php
$report = app(\Gcego\Studio\Studio::class)->health(production: true);

if (! $report->healthy()) {
    // alert with $report->toArray()
}
```

Diagnostics never include secrets. Messages name settings, never their values.
