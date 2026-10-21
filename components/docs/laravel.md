# Laravel integration

`ComponentServiceProvider` is required. Composer installs the Illuminate runtime
dependencies and lists `Gcego\Components\ComponentServiceProvider` in
`extra.laravel.providers`, so Laravel discovers the package automatically.
Laravel 12 and 13 are supported.

If package discovery is disabled, register the provider in
`bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Gcego\Components\ComponentServiceProvider::class,
];
```

Component construction, schema access, builders and runtime services require the
registered provider. Missing registration raises `InvalidConfigurationException`
with instructions instead of silently creating default services.

## Services and factories

The provider is deferred: Laravel loads it when a package service is first
resolved. It registers the following services under `Gcego\Components`:

| Service | Lifetime | Purpose |
| --- | --- | --- |
| `ComponentManager` | Scoped | Package entry point, factories and schema cache |
| `Catalog\ComponentCatalog` | Scoped | Trusted factory aliases and custom registrations |
| `Traversal\ComponentWalker` | Singleton | Path-aware traversal with cycle and depth checks |
| `Validation\PropertyValidator` | Singleton | Property schema checks |
| `Validation\ChildValidator` | Singleton | Child and configuration rules |
| `Validation\ComponentValidator` | Singleton | Validation of the whole tree |
| `Normalization\ComponentNormalizer` | Singleton | Canonical component output |
| `Inspection\ComponentInspector` | Singleton | Inspection snapshots for tooling |
| `Building\ComponentBuilder` | Transient | A fresh builder for each definition |
| `Trees\ComponentTree` | Transient | An independent tree for each root |

Properties, schemas, collections, paths and validation results are data objects.
Construct them normally; they do not have independent service providers or
container singleton bindings.

## Facade

Use `Gcego\Components\Facades\Components` as the package entry point. Laravel
discovers the `Components` alias from the same Composer metadata as the provider:

```php
use Gcego\Components\Component;
use Gcego\Components\Facades\Components;
$root = Components::schema([
    Component::section('dashboard')->components([
        Component::item('statistics'),
    ]),
]);
Components::validate($root)->throwIfInvalid();
$output = Components::normalize($root);
$value = Components::normalizeValue($root->get(Component::Name));
$inspection = Components::inspect($root);
$tree = Components::tree($root);
```

`Components::define(Text::class, ['name', 'Label'])` selects the component's
builder class and resolves a new builder from the container. `make()` builds the
result, including custom constructor adapters.

The facade resolves the provider's request/job-scoped `ComponentManager`. The
manager remains available for constructor injection. Retained managers from an
earlier application or flushed scope are rejected; resolve the manager again
through the facade or container. No manual singleton resolver or fallback
container is created by the package.

Existing declarations remain supported after provider registration:

```php
use Gcego\Components\Properties\Property;

$root = new Component(new Property(Component::Name, 'dashboard'));
$root->assertValid();
$root->normalize();
$root->tree();
```

`Component::define()`, `validate()`, `normalize()` and `tree()` delegate through
the facade to registered services. Their behavior uses the same configuration as
facade calls and constructor injection. Runtime services receive dependencies
through constructor injection without constructing independent defaults.

## Customization

Register overrides in an application service provider's `register()` method,
before resolving package services or validating components:

```php
use Gcego\Components\Traversal\ComponentWalker;

$this->app->singleton(ComponentWalker::class, static fn (): ComponentWalker => new ComponentWalker(maxDepth: 64));
```

The package provider preserves existing instances, factories and aliases. The
configured walker is used by validation, normalization, inspection and trees,
including component convenience methods. Schema definitions and validation caches
are isolated to the active Laravel scope. Validation uses a weak reference to its
manager so reused component instances do not retain an earlier scope. Configure
services before validating components.

There is no configuration file, migration or route to publish. The facade and
provider are discovered automatically.

## Tests and command-line tools

Outside a booted Laravel application, initialize the container through the same
provider before using components:

```php
use Gcego\Components\ComponentServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

$app = new Application(dirname(__DIR__));
Facade::clearResolvedInstances();
Facade::setFacadeApplication($app);
$app->register(ComponentServiceProvider::class);
```

Laravel is also a development dependency so tests and benchmarks exercise a real
application, including deferred provider discovery. It uses the required
provider and runtime dependencies; there is no separate initialization path.

## Namespace migration

Classes are organized by responsibility, with matching PSR-4 folders. Update
imports from the previous root namespaces as follows:

| Previous class under `Gcego\Components` | New namespace under `Gcego\Components` |
| --- | --- |
| `Property` | `Properties\Property` |
| `ComponentCollection`, `PropertyCollection`, `ConfigurationCollection` | `Collections\…` |
| `ComponentDefinition`, `PropertyDefinition`, `Type`, `TypeKind` | `Schema\…` |
| `ComponentTree`, `ComponentPath`, `TreeNode` | `Trees\…` |
| `Layout\NamedComponent` | `Foundation\NamedComponent` |
| `Forms\Field` | `Forms\FieldComponent` |

The base `Component`, `ComponentManager` and `ComponentServiceProvider` live at
`Gcego\Components` in the package root. If using the intermediate
`Services\ComponentManager` or `Providers\ComponentServiceProvider` namespaces,
update those imports to the root namespaces. Existing `Contracts`,
`Building`, `Validation`, `Normalization`, `Inspection`, `Traversal`, `Support`
and `Exceptions` namespaces are unchanged. Tests mirror these folders.

Replace `ComponentManager::instance()->…` calls with `Components::…` using the
facade import. For direct access to the underlying manager, use constructor
injection or `app(\Gcego\Components\ComponentManager::class)`.
Update any manually registered provider name, regenerate Composer autoloading,
and rebuild Laravel's package discovery cache after updating the package.

## Composer registration and distribution

`composer.json` declares the `gcego/components` library name, PSR-4 autoloading,
runtime dependencies, and Laravel provider and facade discovery. Composer handles
package installation; Laravel discovers the root service provider through
`extra.laravel.providers`. No additional package registry or Composer plugin is
needed for this discovery path.

The archive configuration excludes dependencies, test artifacts, local environment
files and developer directories while retaining source code, documentation and
license files. Validate a release with `composer validate --strict`,
`composer audit --locked`, the package tests, and `composer archive`.

## Long-running workers and scope cleanup

Laravel's scoped binding lifecycle releases manager-owned schema definitions at
request or job boundaries. Applications that create their own worker lifecycle
must call `$app->forgetScopedInstances()` at the boundary. The facade does not
keep a second manager cache. Resolve a new manager after scope cleanup; do not
serialize managers into sessions or queues. Debug output redacts their container.

## Executable examples and catalog scope

See the [complete usage guide](usage.md). `examples/run.php` demonstrates standalone
initialization; `examples/full-usage.php` contains only the declaration used inside
an application. `examples/custom-component.php` demonstrates extension classes.

`Components::catalog()` exposes the provider's scoped catalog. For persistent
custom aliases, configure an `afterResolving(ComponentCatalog::class, ...)`
callback in your application provider. One-off registration lasts for the current
scope only. Scope cleanup releases both the manager and catalog; application
callbacks reapply registrations when the catalog is resolved again.
