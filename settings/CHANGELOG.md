# Changelog

## Unreleased

- **Fixed:** settings group and page icons accept every Luminaries name (aliases such as `settings.general`, and `provider:icon`) and typed icons. Previously only bare names were accepted.
- **Added:** the settings navigation shows each page's icon.
- **Fixed:** the definition kind is registered when gcego/registry's `DefinitionKinds` is resolved, not during `register()`. With Laravel's package discovery (alphabetical order) this provider could run before gcego/registry bound the singleton, losing the kind — the package's definitions were then not discovered, and with gcego/workflows installed the application failed to boot.

## 0.1.0 - 2026-10-18

First release.

- **Definitions:** `Setting`, `SettingsSection`, `SettingsGroup` (registry kind `settings`, discovered from `app/GCEGo/Settings`) and `SettingsPage` (kind `settings-page`, from `app/GCEGo/SettingsPages`):
  - positional, named and `Property` arguments, normalized to one `SettingDefinition`;
  - structural validation within a group and across groups (duplicates, types, defaults, options, fields, precedence, unknown dependencies, cycles, pages).
- **Types:** `SettingType` (string, integer, float, boolean, array, json, date, datetime, enum, media), strict `SettingCaster`, JSON-only `SettingSerializer`, explicit nulls kept apart from missing values.
- **Scopes:** global, panel, tenant (institutions) and user; `ScopeContext` from server state only; `ScopeResolver`; per-setting precedence (`ScopePrecedenceResolver`); inheritance and reset.
- **Persistence:** `gcego_settings` (versioned rows), `EloquentSettingsRepository` with row locks and compare-and-swap versions; atomic multi-setting writes; optional partial writes; conflicts refused.
- **Security:** `SettingAccessPolicy` (deny by default; Gate ability, tenant permission and requirements); `SettingsActor` with explicit `asSystem()`; encryption of sensitive values with Laravel's encrypter; redaction in forms, history, events, logs, cache and exports; secret rotation.
- **Validation:** `SettingsValidator`: Laravel rules, options, enums, field rules, custom rule classes, media ownership, `requiredWhen` and `requires` dependencies.
- **Cache:** `SettingsCache` per scope instance with stamps (no stale reads across workers), generation flush, request memory in scoped services.
- **History and events:** append-only `gcego_setting_changes`; after-commit `SettingUpdated`, `SettingReset`, `SecretRotated`, `SettingsUpdated`, `SettingsImported`, `SettingChangeRequested`; structured logging.
- **UI:** `PanelSettingsExtension` (`<panel>/settings`), Livewire `gcego-settings-panel` (navigation, search, history) and `gcego-settings-form` (gcego/forms fields, scope badges, reset with confirmation, unsaved-changes indicator, masked secrets, conflict messages); Tailwind CSS v4 presets and styling slots; English and French.
- **Integration:** gcego/actions handlers (`SaveSettings`, `ResetSettings`), gcego/workflows approvals (`SettingRequest`, `ApplySettingRequest`, `DiscardSettingRequest`, `SettingRequestPolicy`), gcego/notifications (`SettingsNotifier`, `SettingsRecipients`), gcego/media references.
- **Transfer:** `SettingsExporter` and `SettingsImporter` (preview, conflicts, atomic import; sensitive settings never exported or imported).
- **Diagnostics:** `SettingsInspector`, `gcego:settings:inspect`, `gcego:settings:clear-cache`.
