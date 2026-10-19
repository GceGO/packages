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
| `activate()` throws | `BootstrapException` naming the extension; a retry activates only what has not succeeded yet |

Activation order follows dependencies, with ties broken alphabetically.
Duplicate ids from different objects throw `ExtensionException`. Registering
the same object twice is a no-op, and registering after boot throws.

## Inspecting extensions

```php
$gcego->extensions();                        // list<ExtensionDescriptor>
$gcego->hasExtension('tables.export');       // registered
$gcego->isExtensionActive('tables.export');  // activated
$gcego->bootResult()?->skippedExtensions;    // id => reason
$gcego->hasCapability('tables.export');      // from active packages and extensions
```
