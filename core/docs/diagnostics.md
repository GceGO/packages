# Diagnostics

```php
$report = app(Gcego\Core\Gcego::class)->diagnose();

$report->isHealthy();    // no failures
$report->hasWarnings();
$report->failures();     // list<DiagnosticResult>
$report->result('framework.boot')?->status;   // DiagnosticStatus::Passed
$report->toArray();      // for JSON / CLI output
```

`diagnose()` runs on demand only, never on ordinary requests, and it never
throws. A check that crashes becomes a failed result naming the exception class.

## Checks

| Check | Failed when | Warning when |
| --- | --- | --- |
| `php.version` | PHP is older than 8.3.0 | |
| `php.extensions` | a Laravel-required extension (`ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, `tokenizer`) is missing | |
| `laravel.version` | Laravel is older than 13.0.0 | |
| `configuration` | `config('gcego')` is invalid | it contains unknown keys |
| `framework.registration` | the service provider is not registered | |
| `framework.boot` | the last boot failed | the framework is registered but not booted |
| `packages.dependencies` | a registered package has missing, disabled or circular dependencies | |
| `packages.components` | | `gcego/components` is not installed (core does not need it; component packages do) |

Each `DiagnosticResult` has `check`, `status` (`Passed`, `Warning`, `Failed`),
`message` and `details`. Details contain only versions, identifiers, states and
counts. Configuration values and exception messages are never included, because
they could contain secrets. A failed boot reports only the root exception's
class and points to the application log.

## Environment checks during boot

The first three checks are cheap: version comparisons and
`get_loaded_extensions()`, with no network or filesystem access. When
`gcego.environment_checks` is true, `FrameworkBootstrapper` runs them during
boot and refuses to boot if one fails. They are also available on their own:

```php
app(Gcego\Core\Diagnostics\EnvironmentChecker::class)->check();
```

## Custom tooling

A future `gcego/cli` can render `DiagnosticReport::toArray()` directly. Each
row has the shape `{check, status, message, details}`.
