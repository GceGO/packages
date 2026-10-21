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
   with `BootStatus::Disabled`, retained by `bootResult()`;
3. runs the environment checks when `environment_checks` is true;
4. registers every loaded provider that implements `PackageInterface`, then locks package registration;
5. resolves the package boot order, failing on missing, disabled or circular dependencies,
   unsupported declared versions and exclusive-capability conflicts between packages;
6. calls `bootPackage()` on each `Bootable` package, in that order;
7. plans extensions, fails if an exclusive capability would gain a second provider,
   then activates eligible extensions in dependency order;
8. locks extension registration and moves to `Booted`.

Package registration is complete before dependency resolution begins. The lock
remains in place after dependency-resolution, package-hook, or extension failures.
Retries use the same package set and skip successful hooks. A missing package
cannot be registered into that failed application; correct the registrations
and start a fresh application. Failures before registration is locked, such as
invalid configuration or environment checks, leave registration open. Extension
registrations remain repairable until activation succeeds.

The result is a `BootstrapResult`:

```php
$result = Gcego::bootResult();

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

Packages that booted before the failure stay booted: `isPackageActive()` and
`hasCapability()` report them, while `state()` stays `Failed` and
`diagnose()` reports `startup.consistency` as a partial start. Check
`isBooted()`, not individual packages, before relying on the framework.

### Diagnosing a failed automatic boot

With `auto_boot` on, a boot failure is thrown from Laravel's `booted()`
callback, so every HTTP request, queue worker and Artisan command fails loudly,
including `gcego:diagnose`. To inspect the problem, run the read-only tools
with automatic boot switched off (configuration must not be cached):

```bash
GCEGO_STUDIO_AUTO_BOOT=false php artisan gcego:plan
GCEGO_STUDIO_AUTO_BOOT=false php artisan gcego:diagnose
```

With a cached configuration, run `php artisan config:clear` first.

## Manual boot

With `auto_boot` set to false, nothing boots until you ask:

```php
$result = Gcego::boot();
```

## Configuration snapshot

`GcegoConfig` is validated and cached when first resolved, including by a startup
preview. That same immutable snapshot is used by subsequent boot attempts and
retries. Changing Laravel configuration after it has resolved does not change
that snapshot. Invalid configuration does not resolve successfully and may be
repaired before retry. Do not replace a valid snapshot after partial startup:
successful hooks are intentionally not rerun. Apply configuration changes in a
fresh application or restart the worker.

Startup events and per-step timing are described in [startup events](startup-events.md).
Read-only previews are described in [startup planning](startup-planning.md).
