# API reference

## SettingsManager / ScopedSettings

| Method | Returns | Notes |
| --- | --- | --- |
| `get(string $key)` | typed value | Effective value; unknown keys throw `SettingNotFoundException`. |
| `resolve(string $key)` | `ResolvedSetting` | Value, source, version. |
| `many(list $keys)`, `group(string $group)` | `array<key, value>` | One round trip. |
| `has(string $key)` | bool | Registered? |
| `media(string $key)` | `?MediaRecord` | Media settings, through gcego/media authorization. |
| `set($key, $value, ?int $expectedVersion)` | `SettingsWriteResult` | |
| `setMany(array $values, array $expectedVersions = [], bool $partial = false)` | `SettingsWriteResult` | Atomic unless `$partial`. |
| `reset(string\|list $keys, array $expectedVersions = [])` | `SettingsWriteResult` | Back to inherited. |
| `rotate($key, $secret, ?int $expectedVersion)` | `SettingsWriteResult` | Sensitive settings only. |
| `global()`, `forPanel($name)`, `forTenant(Tenant\|Model\|string\|int)`, `forUser(Authenticatable)` | `ScopedSettings` | Choose the level. |
| `actingAs(Authenticatable)`, `asSystem(string $name = 'system')` | `ScopedSettings` | Choose the actor. |
| `definition($key)`, `definitions()` | `SettingDefinition`, `SettingsDefinitions` | Manager only. |
| `register(SettingsGroup\|SettingsPage)` | `DefinitionDescriptor` | Manager only. |
| `flushCache()`, `inspect(bool $runtime = true)` | void, array | Manager only. |

`SettingsWriteResult`: `applied` (key => new version, 0 after a reset),
`unchanged`, `pending` (key => request ULID), `errors` (partial writes),
`correlation`.

## Exceptions (all implement `Gcego\Settings\Exceptions\SettingsException`)

| Exception | HTTP | When |
| --- | --- | --- |
| `SettingNotFoundException` | 404 | Unknown setting, group or page. |
| `SettingAuthorizationException` | 403 | The actor may not do it. |
| `SettingConflictException` | 409 | Outdated version (`->keys`). |
| `InvalidSettingException` | — | Invalid definition or configuration; invalid values (`errors()`, `reason`, `toValidationException()`). |
| `SettingScopeException` | — | Disabled or not allowed scope, or a level the context does not have. |

## Events (after commit, never with values)

`SettingUpdated` (key, group, scope, tenant, change, version, sensitive,
actor, correlation), `SettingReset`, `SecretRotated`, `SettingsUpdated`
(once per write), `SettingsImported`, and `SettingChangeRequested`
(approvals). History change types (`ChangeType`): `updated`, `reset`,
`rotated`, `imported`, `requested`, `approved`, `refused`.

## Extension contracts

| Contract | Default | Purpose |
| --- | --- | --- |
| `Contracts\SettingsRepository` | `EloquentSettingsRepository` | Storage. |
| `Contracts\SettingsAuthorizer` | `SettingAccessPolicy` | Who may see and change what. |
| `Contracts\SettingValidator` | `SettingsValidator` | Casting and validation. |
| `Contracts\SettingsRecipients` | none | Who hears about sensitive changes. |

These are the stable extension points for `gcego/analytics` (listen to
`SettingsUpdated`, read through `SettingsManager`), `gcego/testing` (register
groups with `SettingsManager::register()`, use `asSystem()` for fixtures,
fake the contracts above) and `gcego/cli` (`SettingsInspector::report()`
returns a structured array; `SettingsExporter` / `SettingsImporter`).

## Diagnostics and console

- `php artisan gcego:settings:inspect [--json] [--definitions]` lists groups,
  settings, pages, definition problems, missing tables, stored values that
  no longer decode (or were encrypted with a lost key), values of settings
  that are no longer registered, values at scopes their setting does not
  allow, sensitive values stored in plaintext, missing Gate abilities,
  tenancy and approval-workflow mismatches, encryption availability and
  the effective configuration. It is read-only, examines at most 5,000
  rows, and never shows values. It exits non-zero on problems.
- `php artisan gcego:settings:clear-cache` invalidates every cached value.
- `gcego-settings.observability.log_channel` writes structured entries
  (`gcego-settings.updated`, `reset`, `validation_failed`,
  `authorization_denied`, `invalid_value`) with keys, scopes, actors and
  correlation ids only.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `You may not change (sign in, or use asSystem() in trusted code)` | A write without a signed-in person: use `asSystem('name')` in seeders and commands. |
| `cannot be stored at the tenant scope` | Add `tenant` to the setting's (or group's) `scopes`. |
| `stored at different scopes here` | One unscoped write spans several levels: use `global()` / `forTenant()` / `forUser()`. |
| A tenant page is missing | The request has no verified tenant, or the viewer's membership lacks `settings.manage`. |
| A value "disappeared" after a deploy | Its definition changed type: `gcego:settings:inspect` lists it as invalid; set it again. |
| Changes not visible on another server | The cache store is per server (`array`, `file`): use a shared store. |
