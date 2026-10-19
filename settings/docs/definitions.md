# Definitions

## Setting

```php
new Setting('site_name', 'GCEGO');                                      // name, default
new Setting(name: 'max_upload_mb', default: 10, rules: ['min:1', 'max:100'], scopes: ['global', 'tenant']);
new Setting(new Property(Setting::Name, 'site_name'), new Property(Setting::Default, 'GCEGO'));
```

| Argument / constant | Meaning |
| --- | --- |
| `name` / `Setting::Name` | Key, unique across all groups: `^[a-z][a-z0-9_]{0,63}$`. |
| `label`, `description` | Shown in forms and search (translation keys are fine). |
| `default` / `Setting::Default` | Typed default (dates as `"2026-09-01"`). A named `default: null` is an explicit null default. |
| `type` / `Setting::Type` | A `SettingType` (or its value). When omitted, the default decides it. |
| `nullable` | When omitted: nullable when the default is null or absent. |
| `required` | The value may not be empty. |
| `enum` | The backed enum class of an Enum setting. |
| `options` | Allowed values `value => label` (string and number settings). |
| `rules` | Extra Laravel rules: strings without `|`, or `ValidationRule` class names. |
| `scopes` | Where overrides may be stored (default: the group's, else `['global']`). |
| `precedence` | Lookup order of exactly those scopes, most specific first. |
| `sensitive`, `encrypted` | Masked and redacted everywhere, and encrypted at rest (`encrypted` implies sensitive). |
| `field` | `text`, `textarea`, `email`, `url`, `number`, `toggle`, `checkbox`, `select`, `radio`, `date`, `datetime`, `json`, `media` or `password`. |
| `placeholder`, `keywords`, `span` | Form and search metadata. |
| `requiredWhen`, `requires` | Dependencies (see [validation](validation.md)). |
| `approval` | A gcego/workflows workflow that must approve people's changes. |
| `hidden` | Not shown in forms (still usable from code). |

gcego/security requirements passed as items (`new Ability('...')`,
`new Permission('...')`) are needed to change the setting, together with
the requirements of its section and group.

## SettingsGroup and SettingsSection

```php
return new SettingsGroup(
    'general', 'General',
    new Setting('site_name', 'GCEGO'),
    new SettingsSection('mail', 'Outgoing mail', new Setting('mail_enabled', false), columns: 2),
    scopes: ['global', 'tenant'],     // defaults for its settings
    icon: 'cog', sort: 10, approval: null, hidden: false,
);
```

## Discovery

Every PHP file under `app/GCEGo/Settings` (`gcego-settings.discovery.paths`)
returns a `SettingsGroup`. It can also return the name of a gcego/registry
`DefinitionProvider` class that returns several groups:

```php
// app/GCEGo/Settings/GeneralSettings.php
final class GeneralSettings implements DefinitionProvider
{
    public function definitions(): iterable
    {
        yield new SettingsGroup('general', new Setting('site_name', 'GCEGO'));
    }
}

// app/GCEGo/Settings/general.php
return GeneralSettings::class;
```

Pages live under `app/GCEGo/SettingsPages` (`discovery.pages`). Both
folders use the registry cache (`php artisan optimize`). Packages and
tests can register definitions at runtime with
`SettingsManager::register($group)`.

## Validation of definitions

Each group checks itself when the registry loads it. The checks cover
duplicate keys and sections, enum and type mismatches, defaults that do not
fit their type or options, fields that cannot edit the type, a precedence
that is not exactly the scopes, and self-dependencies. The index of all
groups then checks keys declared in two groups, dependencies on unknown
settings, `requires` cycles, pages of unknown groups, and pages whose scope
no setting allows. Messages name the setting and say what to change.
`gcego:settings:inspect` lists every problem.
