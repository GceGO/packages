# Architecture

gcego/settings keeps every concern in a small class. `SettingsManager` is a thin entry point.

```
Setting / SettingsSection / SettingsGroup / SettingsPage   immutable gcego/morfaw definitions
        │  discovered by gcego/registry (kinds "settings" and "settings-page")
        ▼
SettingsDefinitions ── SettingsDefinitionValidator         one SettingDefinition per key (the internal form)
        │
SettingsManager ─► ScopedSettings (a ScopeContext and an actor)
        │                 │
        │      reads      ▼                   writes
        │      SettingsReader            SettingsWriter
        │        │  ScopePrecedenceResolver    │  SettingsAuthorizer (SettingAccessPolicy)
        │        │  SettingsCache              │  SettingValidator (SettingsValidator)
        │        │  SettingSerializer          │  SettingsRepository (Eloquent)
        │        │  SettingEncryption          │  SettingsHistoryRepository
        │        ▼                             │  SettingApprovals (gcego/workflows)
        │      ResolvedSetting                 ▼  after commit: SettingsCache, events, SettingsNotifier, SettingsLogger
        │
UI:  PanelSettingsExtension ─► SettingsPanel (Livewire) ─► SettingsForm (Livewire)
                                SettingsPageResolver, SettingsSearch, FormSettingsAdapter (gcego/forms fields)
```

## Package boundaries

| Concern | Owned by | Used here through |
| --- | --- | --- |
| Definitions, validation of definitions | gcego/morfaw | `SettingsNode extends Component` |
| Discovery, caching of definitions | gcego/registry | `SettingsKind`, `SettingsPageKind`, `DefinitionProvider` classes |
| Tenancy, requirements, Gate | gcego/security | `TenantContext`, `TenantAuthorizer`, `SecurityManager::check()` |
| Fields, rendering, normalization | gcego/forms | `FormSettingsAdapter`, `FormManager::process()`, `FormRenderFrame` |
| Rendering | gcego/runtime | `Runtime::render()` |
| Classes and themes | gcego/chroma | slots on `SettingsGroup`, `control.button` presets |
| Panel routes and shell | gcego/panels | `PanelSettingsExtension`, `PanelManager::respond()` |
| Action handlers | gcego/actions | `SaveSettings`, `ResetSettings` |
| Approvals | gcego/workflows | `SettingRequest` as the subject, `ApplySettingRequest` / `DiscardSettingRequest` automations |
| Notifications | gcego/notifications | category `settings`, `SettingsNotifier` |
| Files | gcego/media | Media settings store a ULID that is checked through `MediaManager` |
| Cache, encryption, validation, database | Laravel | `Cache`, `StringEncrypter`, `Validator`, Eloquent |

gcego/studio is not part of the repository yet. The package describes itself
through `SettingsServiceProvider::descriptor()` (the gcego/core
`PackageInterface`), so a future Studio can list it.

## Lifecycles

- **Registration:** `register()` merges configuration, binds services and
  registers the registry kinds. Discovery roots are added lazily. Nothing is
  loaded from the database, the cache or the definition files.
- **Read:** `ScopeResolver` builds a `ScopeContext` from the request. For each
  setting, `SettingsReader` takes the candidate levels from the setting's
  precedence, loads every needed scope instance at once (cache, or one query
  for all misses), decodes the first valid stored value, and falls back to
  the default.
- **Write:** see `SettingsWriter`. The steps are registered keys, allowed
  scopes, authorization, casting and validation (with dependencies), then one
  transaction (row locks, version checks, history, approval requests). After
  the commit come cache invalidation, events, notifications and logs.

Services that read the request are bound as *scoped*. Laravel forgets them
after every request and queued job, Octane included.
