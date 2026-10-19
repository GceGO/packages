# Lifecycle

Laravel's provider lifecycle is authoritative. GCEGO's states only describe how
far the framework itself has progressed inside it.

```text
Uninitialized ──register()──▶ Registered ──boot()──▶ Booting ──▶ Booted
                                  ▲                     │
                                  └──── disabled ───────┤
                                                        ▼
                               boot() again ◀────── Failed
```

| State | Meaning |
| --- | --- |
| `Uninitialized` | `GcegoServiceProvider` has not registered. |
| `Registered` | Services are bound; the framework has not booted, is disabled, or has `auto_boot` off. |
| `Booting` | `FrameworkBootstrapper::boot()` is running. |
| `Booted` | Packages are booted and extensions activated; registration is locked. |
| `Failed` | The last boot attempt threw; `LifecycleManager::failure()` holds the exception. |

Any other transition throws `InvalidLifecycleException`. For example,
calling `Gcego::boot()` from inside a package boot hook is a re-entrant boot.

## Boot sequence

`GcegoServiceProvider::boot()` registers an `$app->booted()` callback, so
GCEGO boots after **every** provider has booted. The callback then:

1. moves `Registered` (or `Failed`) to `Booting`;
2. builds `GcegoConfig`, and when `enabled` is false, returns to `Registered`
   with `BootStatus::Disabled`;
3. runs the environment checks when `environment_checks` is true;
4. registers every loaded provider that implements `PackageInterface`;
5. resolves the package boot order, failing on missing, disabled or circular dependencies;
6. calls `bootPackage()` on each `Bootable` package, in that order;
7. activates eligible extensions in dependency order;
8. locks package and extension registration and moves to `Booted`.

The result is a `BootstrapResult`:

```php
$result = app(Gcego::class)->bootResult();

$result->status;             // BootStatus::Booted
$result->packages;           // ['gcego/core', 'gcego/tables', 'gcego/forms']
$result->extensions;         // ['tables.export']
$result->skippedExtensions;  // ['forms.table-filters' => 'package gcego/tables is not active']
$result->durationMs;         // measured boot time
```

## Idempotency

- Registering the provider twice keeps one instance and one `Registered` state.
- Calling `boot()` again after a successful boot returns the same `BootstrapResult` and does no work.
- Registering the same package or extension object twice is a no-op.

## Failures and recovery

Any exception during boot moves the state to `Failed` and is rethrown as
`BootstrapException`, with the original exception available as
`getPrevious()`. Package and extension failures name the culprit:

```text
GCEGO package "acme/flaky" failed to boot: cache warming failed
```

`boot()` may be called again. Packages and extensions that already succeeded
are not initialized a second time; only the remaining work runs.

## Manual boot

With `auto_boot` set to false, nothing boots until you ask:

```php
$result = app(Gcego::class)->boot();
```
