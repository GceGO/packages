# gcego/settings

Declarative settings for GCEGO Admin Studio. You declare settings in PHP.
The package does the rest:

- typed values with strict casting, defaults and nullability;
- global, panel, tenant (institution) and user scopes, with explicit precedence;
- authorization on every write, deny by default;
- encrypted sensitive values, never shown, logged, cached in plaintext or exported;
- Laravel validation, plus dependencies between settings;
- atomic, versioned writes that refuse to overwrite concurrent changes;
- a cache shared by every worker, invalidated on every write;
- an append-only history;
- generated settings pages in every gcego/panels panel (gcego/forms fields, Livewire, Alpine.js, Tailwind CSS v4);
- search, import and export, approvals through gcego/workflows, notifications, media references and diagnostics.

```php
// app/GCEGo/Settings/general.php
use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;
use Gcego\Settings\Types\SettingType;

return new SettingsGroup(
    'general',
    new Setting('site_name', 'GCEGO'),
    new Setting(name: 'maintenance_mode', default: false, type: SettingType::Boolean),
    new SettingsSection('mail', 'Outgoing mail',
        new Setting(name: 'mail_enabled', default: false),
        new Setting(name: 'mail_from_address', default: '', field: 'email', requiredWhen: ['mail_enabled' => true]),
    ),
    new Setting(name: 'external_api_secret', default: null, encrypted: true),
);
```

```php
use Gcego\Settings\SettingsManager;

$settings = app(SettingsManager::class);

$settings->get('site_name');                                    // 'GCEGO'
$settings->set(key: 'site_name', value: 'GCEGO Learning Platform');
$settings->forTenant($institution)->get('school_name');
$settings->forUser($user)->get('preferred_theme');
$settings->global()->asSystem('seeder')->set('maintenance_mode', true);
```

The same definition can be written in three styles. All of them produce one
internal definition:

```php
new Setting('site_name', 'GCEGO');
new Setting(name: 'site_name', label: 'Website Name', default: 'GCEGO');
new Setting(
    new Property(Setting::Name, 'site_name'),
    new Property(Setting::Label, 'Website Name'),
    new Property(Setting::Default, 'GCEGO'),
);
```

Every group gets a page under `<panel>/settings`. To edit a group at another
scope, or to give a page its own title, declare a page:

```php
// app/GCEGo/SettingsPages/school.php
return new SettingsPage(name: 'school', title: 'School settings', group: 'institution', scope: SettingScope::Tenant);
```

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4;
- gcego/core, components, registry, runtime, styling, security, actions, forms,
  panels, notifications, media and workflows.

Install the package, then run its migrations (`php artisan migrate`). The
tables are `gcego_settings`, `gcego_setting_changes` and
`gcego_setting_requests`. To change the defaults, publish the configuration:
`php artisan vendor:publish --tag=gcego-settings-config`.

Define the Gate abilities your platform administrators need:

```php
Gate::define('gcego-settings.manage', fn (User $user) => $user->isPlatformAdmin());
Gate::define('gcego-settings.export', fn (User $user) => $user->isPlatformAdmin());
Gate::define('gcego-settings.import', fn (User $user) => $user->isPlatformAdmin());
```

Institution administrators need the tenant permission `settings.manage` from
your gcego/security `TenantResolver`.

## Security model, in short

| Rule | What it means |
| --- | --- |
| Only registered settings | An unknown key is refused (`SettingNotFoundException`). Nothing creates a setting on the fly, so no request can write `app.key` or `is_admin`. |
| Scopes are declared | A setting is stored only at the scopes it allows. A tenant can never override a platform-only setting. |
| The server picks the scope | Tenant, panel and user identities come from the verified request (gcego/security, gcego/panels, the guard), never from the browser. A settings form opened for one tenant refuses requests made in another (403). |
| Deny by default | Every write is authorized for the person making it. Guests are refused. Trusted code must say `asSystem()` explicitly. |
| Secrets stay secret | Sensitive values are encrypted with Laravel's encrypter before they are stored. They are masked in forms and left out of the history, events, logs and exports, and they are never cached in plaintext. |
| No lost updates | Writes carry the version they saw. A write with an outdated version is refused (`SettingConflictException`); the newer value is kept. |
| All or nothing | A write of several settings applies all of them or none (unless you ask for a partial write). |

## Documentation

- [Architecture](docs/architecture.md)
- [Definitions: settings, groups, sections, discovery](docs/definitions.md)
- [Types, casting, defaults and nulls](docs/types.md)
- [Scopes, precedence and inheritance](docs/scopes.md)
- [Tenancy and user preferences](docs/tenancy.md)
- [Validation and dependencies](docs/validation.md)
- [Authorization](docs/authorization.md)
- [Encryption and sensitive settings](docs/encryption.md)
- [Caching and long-running workers](docs/caching.md)
- [Settings pages, forms, history, search, import and export, approvals, notifications, media](docs/settings-pages.md)
- [API reference, events, diagnostics and troubleshooting](docs/api-reference.md)

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan (max)
composer format    # Pint
```
