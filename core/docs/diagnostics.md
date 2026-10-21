# Core diagnostics

Core inspects its own infrastructure and the basic Laravel/PHP environment.
It does not implement application authentication, authorization, secret storage,
security scanning, database health, or functionality belonging to
`gcego/security` or `gcego/vault`.

```php
$report = app(Gcego\Core\Gcego::class)->diagnose();
$report->health();                    // healthy, warning, critical
$report->isHealthy();                 // compatibility API: no errors, even if warnings exist
$report->result('packages.inventory');
$report->failures();
$report->warnings();
$report->toArray();                   // rows, retained for existing consumers
$report->toStructuredArray();         // health, healthy, generatedAt, checks
$report->publicSummary();             // health, healthy, generatedAt only
json_encode($report, JSON_THROW_ON_ERROR); // same as toStructuredArray()
```

Reports are snapshots, generated on demand in UTC. No diagnostics run on
ordinary requests unless the application explicitly calls them. Groups and
custom checks are isolated: a crashing check becomes an error containing its
exception class, while independent checks continue. Checks never invoke GCEGO
package hooks, extension activators, external commands, or network requests.
Contributed checks own their I/O and should use bounded timeouts.

## Reporting contract

Every row contains:

| Field | Meaning |
| --- | --- |
| `check`, `code` | Stable machine-readable check identifier |
| `status` | Compatibility status: `passed`, `warning`, `failed` |
| `severity` | `info`, `warning`, `error` |
| `message` | Human-readable explanation |
| `details` | Scalar metadata or lists of scalars |
| `resolution` | Recommended corrective action for warnings/errors |
| `package`, `extension` | Related component ID when applicable |

Component inventory and version codes include their component ID. A report's
`generatedAt` is an ISO 8601 timestamp. Overall health is `critical` when any
check fails, `warning` when only warnings exist, otherwise `healthy`.
`isHealthy()` and the JSON `healthy` field retain their prior meaning of no
failed checks; use `health()` when warnings must affect readiness decisions.

## Coverage

| Area | Codes / contents |
| --- | --- |
| Identity and environment | `framework.identity`, `framework.build`, `composer.runtime`, `runtime.platform`, `application.environment`, `php.version`, `php.extensions`, `laravel.version` |
| Lifecycle | `framework.registration`, `framework.boot`, `lifecycle.history`, `lifecycle.transitions`; accepted/rejected transitions, current state and bounded transition history |
| Registration and discovery | `packages.discovery`, `packages.inventory`, `packages.status.<id>`, `packages.order`; registered versus discovered counts, versions, capabilities, status, attempts, resolved and actual order |
| Package dependencies | `packages.dependencies`, `packages.dependencies.missing`, `.disabled`, `.circular`, `.unsupported_version`, `packages.capabilities.exclusive`, `packages.ordering`, `packages.versions.<id>`, `packages.composer.<id>` |
| Extensions | `extensions.inventory`, `extensions.status.<id>`, `extensions.order`, `extensions.dependencies`, `.missing`, `.disabled`, `.circular`, `.capabilities`, `extensions.capabilities.exclusive`; eligibility, skips, actual order, failed attempts and requirements |
| Configuration | `configuration`, `configuration.required`, `.deprecated`, `.dynamic_classes`, `.packages`, `.extensions`, `.environment`, `laravel.configuration_cache` |
| Laravel integration | `laravel.provider`, `laravel.bindings`, `laravel.lifecycle`, `laravel.deferred_providers`, `laravel.commands` |
| Failure analysis | `startup.failure`, `startup.consistency`; safe root exception class, failure component, blocked package/extension dependants, partial startup |
| Capability integrity | `capabilities.available`, `capabilities.providers.<capability>`, `capabilities.required.<extension>`; active providers, required capabilities, exclusive-provider conflicts |
| Performance | `performance.bootstrap`, `performance.package.*`, `performance.extension.*`; phase totals, optional detailed attempts and slow-hook warnings |
| Operations | `security.production_debug`, `security.diagnostic_access`, `security.redaction`; production debug and unsafe Core environment combinations |
| Rejected operations | `packages.registration.duplicate`, `.late`, `.mutation`, `extensions.registration.*`, `packages.descriptor.invalid`, `extensions.descriptor.invalid`, `packages.discovery.failed`, `packages.provider.deferred`, `framework.recursive_boot`, `packages.recursive_boot`, `extensions.recursive_boot`, `startup.observer_failed` |

Core does not inspect other GCEGO packages. The `packages.components` check
was removed in 1.1.0; packages that want an availability or health check
contribute one through `DiagnosticRegistry`.

When package dependencies are broken, `extensions.dependencies` is a warning
pointing at `packages.dependencies`, because extensions cannot be planned
without a package order.

## Collection and interpretation

Package discovery reads loaded providers implementing `PackageInterface` and
combines them with registered metadata in an isolated snapshot. Ordinary
Laravel providers are not inferred to be GCEGO packages. Core cannot record
errors that occur before an operation reaches Core, such as an exception while
an application constructs its own descriptor outside a registration call.
Snapshots do not reopen a closed live registration set. New providers added
after closure are reported as discovery errors rather than included as bootable
packages. Package registration closes before dependency resolution or hooks;
the lock remains closed after failure. Correct registrations and create a fresh
application to change that set.

Historical registration incidents and transition history retain the latest 100
entries each. Rejected operations remain warnings after recovery, while current
failed components cause errors. Successful retries clear the step's current
failure status, retain attempt counts, and never repeat successful hooks.
Detailed timing history, when enabled, retains earlier failed attempts.

Deferred-provider inspection rejects loaded GCEGO providers that violate the
non-deferred contract. It inspects known class definitions and reports the count
of unloaded provider classes left uninspected; it does not autoload or instantiate
arbitrary deferred providers. Container checks resolve only a fixed allowlist of
Core services. Command checks verify Core registration milestones and command
class resolution without bootstrapping the entire Artisan kernel.

Core supports PHP `^8.3` and Laravel `^13.0`, including upper version bounds.
Build references come from Composer metadata when available. Composer runtime
API version is reported only when the runtime exposes its version constant;
otherwise it is explicitly unavailable, not inferred from the Composer CLI.

All essential Core configuration has defaults, so there are currently no
additional required keys or deprecated keys. Unknown settings warn. Core accepts
no configured dynamic class references; unsupported class options are ignored
without autoloading their values. Disabled-package/extension references are
checked against known metadata and unknown references warn.

Composer mismatch checks compare descriptor versions with Composer's normalized
installed version when both are reliably available. They do not re-resolve the
application's complete Composer dependency graph or read application manifests.
Missing version metadata is explicitly reported when a declared constraint needs
it. See [package declarations](packages.md) for `versionConstraints`.

## Profiling and thresholds

```php
// config/gcego.php
'diagnostics' => [
    'profiling' => false,
    'slow_boot_ms' => 100.0,
    'slow_package_ms' => 50.0,
    'slow_extension_ms' => 50.0,
],
```

`GCEGO_STUDIO_PROFILING=true` enables detailed profiling using the package's
published default configuration. The default is off. Thresholds must be positive
finite numbers. Lifecycle state, safe exception classes, attempt counts and a
few aggregate phase timings remain available with profiling off. Per-step
`StartupTiming` records and per-step clock reads are omitted when disabled;
step completion/failure event `durationMs` is then `null`.

Aggregate phase timings cover provider discovery/registration, dependency
resolution, package initialization and extension activation. The latest attempt
replaces phase totals, including when that phase fails. Not-yet-reached phases
are `null`; totals are not silently carried over from previous attempts. Detailed
attempt records include failed attempts and retries. Slow step warnings require
profiling; total-startup warnings work without it. These are GCEGO bootstrap
measurements, not total Laravel startup or request profiling.

## Safe access and redaction

Core registers no HTTP diagnostic routes. Detailed reports are operational
information and must be restricted by the host application's authorization.
Use `publicSummary()` for public uptime endpoints; it deliberately omits package
names, environment data, versions, messages, exception classes and paths. Core
does not implement access-control policies belonging to security packages.

Built-in checks return infrastructure metadata, never raw configuration values,
exception messages, stack traces, environment dumps or secret files. JSON
serialization of result/report objects uses their structured serializers.
Recognizable credentials in returned text, credential-bearing URLs,
authorization headers and sensitive detail keys are redacted. Nested or
object-valued details are replaced rather than serialized, and text is bounded.
Anonymous exception classes do not expose their source-file paths.

A pattern-based redactor cannot recognize every arbitrary secret. Contributed
checks must return metadata only and must not deliberately embed credentials in
messages, IDs, explanations, resolutions or details. Do not expose detailed
reports publicly even when redaction is enabled.

## Artisan

```bash
php artisan gcego:diagnose
php artisan gcego:diagnose --json
php artisan gcego:diagnose --json --strict
```

The table contains code, severity, explanation and resolution. JSON contains the
structured report envelope. Exit status is 0 with no errors and 1 for errors;
`--strict` also exits 1 for warnings. Strict mode does not change the JSON health
semantics.

Laravel must start before Artisan executes a command. If automatic GCEGO startup
fails, set `GCEGO_STUDIO_AUTO_BOOT=false` and refresh cached configuration when
necessary. This allows inspection without executing package hooks, but does not
repair configuration or dependencies. `gcego:plan` provides a separate startup
preview; see [startup planning](startup-planning.md).

## Package-contributed checks

```php
use Gcego\Core\Contracts\DiagnosticCheck;
use Gcego\Core\Diagnostics\DiagnosticRegistry;
use Gcego\Core\Diagnostics\DiagnosticResult;

final class ExportHealth implements DiagnosticCheck
{
    public function id(): string { return 'exports.storage'; }
    public function run(): DiagnosticResult
    {
        return DiagnosticResult::passed($this->id(), 'Export storage is configured.');
    }
}

// In a provider's register() method:
$this->app->make(DiagnosticRegistry::class)->register(new ExportHealth);
```

Checks run after Core checks, sorted by ID. Core-owned diagnostic namespaces
cannot be used by contributed checks. Re-registering the same instance is a
no-op; duplicate IDs, recursive execution and new registrations during a run
throw `DiagnosticException`. A mismatched result ID becomes a failed check.
Failures in one check do not suppress other checks. Checks are framework-wide
objects: keep request, user, session and tenant state out of their properties.
