# Extensions

A `StudioExtension` is trusted code (your application, or a package you have vetted). Studio does not load extensions from user input or the database.

```php
interface StudioExtension
{
    public function id(): string;          // "vendor/name" or "name": [a-z0-9][a-z0-9_.-]*
    public function priority(): int;       // lower first; ties by id
    public function register(StudioExtensionContext $context): void;
}
```

The context accepts:

| Method | Contribution |
| --- | --- |
| `healthCheck(class-string<StudioHealthCheck>)` | a check in `gcego:studio:health` |
| `installationStep(class-string<InstallationStep>)` | files planned by `gcego:install` (same conflict rules, inside the application only) |
| `panelExtension(class-string<PanelExtension>)` | passed to `PanelManager::extend()`; its routes run inside each panel's middleware |
| `inspectorSection(string $name, Closure)` | a section `<id>:<name>` in `gcego:studio:inspect` |

## Registration

```php
// config/gcego-studio.php
'extensions' => [App\Studio\ReportsExtension::class],

// or from a service provider's register() or boot()
app(\Gcego\Studio\Studio::class)->extend(App\Studio\ReportsExtension::class);
```

Rules (an `ExtensionException` explains each violation):

- ids are well formed and unique, and two classes claiming one id are refused;
- the same class registered twice counts once;
- contributions must implement the right contract;
- registering after Studio applied extensions (after boot) is refused, because panel routes have been built by then.
