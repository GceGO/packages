# Configuration

## Options

```php
return [
    'enabled' => env('GCEGO_STUDIO_ENABLED', true),
    'name' => env('GCEGO_STUDIO_NAME', 'GCEGO Admin Studio'),
    'auto_boot' => env('GCEGO_STUDIO_AUTO_BOOT', true),
    'environment_checks' => env('GCEGO_STUDIO_ENVIRONMENT_CHECKS', true),
    'packages' => ['disabled' => []],
    'extensions' => ['disabled' => []],
];
```

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | When false, services register but no package or extension boots (`BootStatus::Disabled`). |
| `name` | non-empty string, at most 120 characters | `GCEGO Admin Studio` | Returned by `Gcego::name()`; surrounding whitespace is trimmed. |
| `auto_boot` | bool | `true` | Boot once Laravel has booted. When false, call `app(Gcego::class)->boot()` yourself. |
| `environment_checks` | bool | `true` | Run the PHP/Laravel checks during boot and refuse to boot if one fails. |
| `packages.disabled` | list of Composer names | `[]` | Installed GCEGO packages that should not boot. |
| `extensions.disabled` | list of extension ids | `[]` | Registered extensions that should not activate. |

## Validation rules

- **Booleans** accept `true`/`false`, `0`/`1`, and the strings `env()` returns
  (`"1"`, `"0"`, `"true"`, `"false"`, `"yes"`, `"no"`, `"on"`, `"off"`).
- **`null` and `""`** fall back to the default.
- **Unknown keys** produce warnings in diagnostics, not errors.
- **Invalid values** throw `ConfigurationException` when the configuration is
  first used. The message lists every invalid key with its expected and actual
  *type*. Values are never echoed, so a misplaced secret cannot leak into logs.

```text
Invalid GCEGO configuration (config/gcego.php):
- gcego.enabled must be a boolean, a string given.
- gcego.packages.disabled.0 must be one of Composer package names such as "gcego/tables", a string given.
```

When `auto_boot` is on, invalid configuration makes the automatic boot fail
with `BootstrapException`. Its previous exception is the
`ConfigurationException`, and the lifecycle state becomes `Failed`.

## Typed access

```php
use Gcego\Core\Configuration\GcegoConfig;

$config = app(GcegoConfig::class);   // or app(Gcego::class)->config()

$config->enabled();
$config->name();
$config->autoBoot();
$config->environmentChecks();
$config->disabledPackages();
$config->isPackageDisabled('gcego/analytics');
$config->disabledExtensions();
$config->isExtensionDisabled('tables.export');
$config->toArray();
```

`GcegoConfig` is immutable and built once per application. Change
configuration in a service provider's `register()` or `boot()` method, before
GCEGO boots. Later changes to `config('gcego')` are not picked up.

## Caching

`env()` is only called inside `config/gcego.php`, so `php artisan config:cache`
works as usual. Runtime services read only Laravel's config repository.
