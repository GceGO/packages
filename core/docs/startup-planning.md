# Startup planning and dependency inspection

```php
use Gcego\Core\Facades\Gcego;

$plan = Gcego::plan();

$plan->isValid();
$plan->enabled;
$plan->packages;           // enabled package IDs, dependencies first
$plan->extensions;         // eligible extension IDs, dependencies first
$plan->skippedExtensions;  // extension ID => reason
$plan->disabledPackages;   // configured disabled package IDs
$plan->capabilities;       // sorted unique prospective capabilities
$plan->problems;           // configuration, discovery or dependency problems
$plan->toArray();
```

Planning creates a temporary registration snapshot containing explicitly
registered packages and loaded Laravel providers implementing `PackageInterface`.
It does not mutate the live package registry, lifecycle, boot results, timings,
or activation state. It never calls package boot hooks or extension activators.
Already registered package descriptors use cached metadata. Descriptors from
newly discovered providers must be pure metadata methods without side effects.
Deferred providers are not loaded by planning or boot discovery.

A plan is a preview of known metadata, not a guarantee that hooks will succeed.
Packages may register additional extensions during their boot hooks, and
startup listeners may contribute registrations before initialization begins.
Those contributions cannot appear in a preview taken before they exist.

Package problems are collected together. If package resolution fails, package
order and extension planning are omitted. Extension planning reports its first
error. Invalid configuration and discovery failures appear in `problems`;
unexpected failures expose the exception class, not its message. Environment
requirements and runtime integrations remain the responsibility of diagnostics.

`enabled` describes the global framework switch. The plan still validates and
shows eligibility when disabled; these steps will not execute while disabled.
A valid plan can therefore have `enabled === false`.

## Artisan

```bash
php artisan gcego:plan
php artisan gcego:plan --json
```

Exit status is 0 for a valid plan, including a deliberately disabled framework,
or 1 for a plan with problems. JSON contains the same fields as `toArray()`.
Laravel still starts normally before executing the command. To inspect a plan
before GCEGO hooks execute, set `GCEGO_STUDIO_AUTO_BOOT=false` and refresh cached
configuration when necessary. The command itself never calls `boot()`.

## Dependency queries

```php
$gcego->packageDependencies('gcego/forms');
$gcego->packageDependencies('gcego/forms', transitive: true);
$gcego->packageDependencies('gcego/forms', transitive: true, includeOptional: true);
$gcego->packageDependants('gcego/core', transitive: true);
$gcego->capabilityProviders('tables');
// ['packages' => ['gcego/tables'], 'extensions' => []]
```

Dependency queries inspect the live registered metadata, return sorted IDs,
and throw `PackageRegistrationException` for an unknown starting package.
They include required edges by default. Optional edges are opt-in. Missing
requirements are retained in dependency results and cycles do not cause loops.
Disabled registrations remain visible in these metadata queries.

Capability providers are active packages and extensions only, grouped
separately and sorted. They report availability rather than resolving services.
Before manual discovery, use `plan()` to inspect loaded providers; metadata
queries do not perform discovery implicitly.
