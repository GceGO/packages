# Packages

A GCEGO package is an ordinary Composer package with an ordinary Laravel
service provider. Composer installs it and Laravel loads it. GCEGO adds three
things: a descriptor, dependency validation, and an optional boot hook that
runs in dependency order.

## The contract

```php
interface PackageInterface
{
    public function descriptor(): PackageDescriptor;
}

interface Bootable // optional
{
    public function bootPackage(BootstrapContext $context): void;
}
```

Implement them on the package's existing service provider:

```php
final class FormsServiceProvider extends ServiceProvider implements PackageInterface, Bootable
{
    public function register(): void
    {
        // Normal Laravel bindings.
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/forms',
            requires: ['gcego/core'],
            optional: ['gcego/tables'],
            capabilities: ['forms'],
            description: 'Form components.',
        );
    }

    public function bootPackage(BootstrapContext $context): void
    {
        if ($context->hasPackage('gcego/tables')) {
            // Integrate with tables; it has already booted.
        }
    }
}
```

The provider must not be deferred. To register a package that is not a
provider:

```php
app(PackageManager::class)->register($package);
```

Do this before GCEGO boots, from a provider's `register()` or `boot()` method.
Later registrations throw `PackageRegistrationException`.

## Descriptor fields

| Field | Rule |
| --- | --- |
| `id` | The Composer package name, such as `gcego/tables`. |
| `version` | Optional. When omitted, the version is read from Composer's installed metadata (`null` if unknown). |
| `requires` | GCEGO packages that must be registered and enabled. They boot first. |
| `optional` | GCEGO packages that boot first when present. Their absence is fine. |
| `capabilities` | Lowercase dotted names that `Gcego::hasCapability()` reports while the package is active. |
| `description` | Free text. |

Descriptors are validated when they are built. Invalid ids, self-dependencies
and packages listed as both required and optional throw
`PackageRegistrationException`.

`requires` describes **framework-level** relationships between GCEGO packages.
Library dependencies such as `gcego/morfaw` belong in `composer.json`;
Composer remains responsible for installation and version constraints.

## Dependency rules

| Situation | Result |
| --- | --- |
| A required package is not registered | `PackageDependencyException`: `... requires "gcego/data", which is not registered. Install it with "composer require gcego/data" ...` |
| A required package is in `gcego.packages.disabled` | `PackageDependencyException`, telling you to disable the dependant too |
| A cycle exists (including through `optional`) | `PackageDependencyException`: `Circular GCEGO package dependency: gcego/a -> gcego/b -> gcego/a.` |
| An optional package is absent or disabled | No error; ordering ignores it |

Any of these errors makes the boot fail with `BootstrapException`.

## Ordering

Dependencies always boot first. Ties are broken alphabetically by id, so the
order does not depend on the order in which Laravel loaded the providers. The
algorithm is iterative, so dependency chains cannot overflow the stack.

## Duplicates

Registering the same object twice is a no-op. A different object claiming an
already registered id throws `PackageRegistrationException`. Packages are
never silently replaced.

## `gcego/core` itself

`GcegoServiceProvider` is the package `gcego/core`, with capability
`framework`. Every other package can declare `requires: ['gcego/core']`.

## Registration during initialization

Register packages before Core resolves dependencies. After discovering loaded
providers, Core locks package registration before dependency resolution and
boot hooks begin. New package registrations
inside `bootPackage()` are rejected because dependency resolution has already
finished. Direct recursive calls to `PackageManager::boot()` are rejected too.
The execution guard clears after failure, but Core's package-registration lock
remains in place. Retries retain the same registered package set. To add a missing
package after resolution has started, correct the registrations and create a
fresh application.
A package may still register extensions during its boot hook, before extension
activation starts.

## Declared version compatibility

```php
new PackageDescriptor(
    id: 'gcego/reports',
    requires: ['gcego/core'],
    versionConstraints: ['gcego/core' => '^1.0'],
);
```

Constraints use Composer semver syntax and may target only declared required or
optional GCEGO packages. Invalid declarations are rejected. Known incompatible
versions fail dependency resolution before hooks run. Constraints whose installed
package has no version metadata are reported as unverifiable warnings; Core does
not invent a version. Optional dependencies remain optional when absent. Composer
continues to manage library installation and its own version constraints.

`exclusiveCapabilities` may name a subset of a package's declared `capabilities`.
Multiple active providers are informational by default. If any active provider
declares a capability exclusive, diagnostics report a conflict when multiple
providers are active. Exclusivity is an integrity diagnostic, not an automatic
provider-selection or rollback mechanism.
