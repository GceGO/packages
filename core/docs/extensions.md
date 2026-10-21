# Extensions

An extension is a named, independently switchable framework capability, such as
`tables.export`. A package may contribute several, and an application may
register its own. Extensions activate once, after every package has booted.

## Contract

```php
interface ExtensionInterface
{
    public function descriptor(): ExtensionDescriptor;

    public function activate(BootstrapContext $context): void;
}
```

Register extensions explicitly, typically from the owning package's `register()`:

```php
use Gcego\Core\Extensions\ExtensionManager;

public function register(): void
{
    $this->app->make(ExtensionManager::class)->register(new TableExportExtension);
}
```

## Descriptor

```php
new ExtensionDescriptor(
    id: 'forms.table-filters',
    requires: ['forms.core'],          // must be active; activates first
    optional: ['tables.export'],       // activates first when active
    whenPackages: ['gcego/tables'],    // skipped (not an error) unless these packages are active
    capabilities: ['forms.table-filters'],
    description: 'Filter forms for table columns.',
);
```

`whenPackages` is how optional integrations switch themselves on. A forms
package can ship a tables integration that only activates when `gcego/tables`
is installed and enabled.

## Activation rules

| Situation | Result |
| --- | --- |
| Listed in `gcego.extensions.disabled` | skipped, reason `disabled in gcego.extensions.disabled` |
| A `whenPackages` package is inactive | skipped, reason `package gcego/tables is not active` |
| A required extension was skipped for a package reason | skipped too, reason `required extension ... is inactive` |
| A required extension is not registered | `ExtensionException` |
| A required extension is disabled by configuration | `ExtensionException`, telling you to disable the dependant too |
| A dependency cycle exists | `ExtensionException` |
| Activating it would give an exclusive capability a second active provider | `ExtensionException` (`extensions.capabilities.exclusive`), before any extension activates |
| `activate()` throws | `BootstrapException` naming the extension; a retry activates only what has not succeeded yet |

Eligibility and transitive package-based skips are resolved before validating
requirements, so registration order does not cause an ineligible extension to
fail startup. Missing and disabled requirements are errors for eligible extensions.

Activation order follows dependencies, with ties broken alphabetically.
Duplicate ids from different objects throw `ExtensionException`. Registering
the same object twice is a no-op, and registering after boot throws. New
registrations during activation are rejected because they would miss the
activation plan. Recursive activation is also rejected. After a failure, the
activation guard is cleared so registration and retry remain possible.

## Inspecting extensions

```php
$gcego->extensions();                        // list<ExtensionDescriptor>
$gcego->extension('tables.export');          // descriptor, throws when unknown
$gcego->capabilities();                     // all active capabilities, sorted
$gcego->hasExtension('tables.export');       // registered
$gcego->isExtensionActive('tables.export');  // activated
$gcego->bootResult()?->skippedExtensions;    // id => reason
$gcego->hasCapability('tables.export');      // from active packages and extensions
```

## Required and exclusive capabilities

`ExtensionDescriptor::requiresCapabilities` lists capabilities that must be
available before activation. Package capabilities are available first. To consume
a capability from another extension, declare that extension in `requires` so its
activation precedes the consumer. A capability provided only by the consuming
extension itself cannot satisfy its own prerequisite. Missing prerequisites fail
planning and activation before any extension hooks execute.

`exclusiveCapabilities` is an optional subset of provided capabilities. Once
any active package or planned extension declares a capability exclusive, a
second provider fails startup after package hooks and before any extension
activates (since 1.1.0); planning and diagnostics report the same conflict.
Ordinary duplicate providers remain informational. Core does not select a
winning provider.

## Ownership

Extensions have no implicit owner. Disabling a package in
`gcego.packages.disabled` does not disable extensions its provider registered,
because Laravel still runs that provider. To tie an extension to the package
that ships it, list the package in `whenPackages`:

```php
new ExtensionDescriptor('reports.scheduling', whenPackages: ['vendor/reports']);
```

The extension is then skipped whenever `vendor/reports` is disabled or failed to
register. Successful registration only means the identifier was free. It is
never an authorization decision: extensions must still enforce the
application's own authorization for anything they expose.
