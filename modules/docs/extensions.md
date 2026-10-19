# Extensions, lifecycle and diagnostics

## Extensions

```php
// config/gcego-modules.php
'extensions' => [App\GCEGo\AuditPages::class],

app(ModuleManager::class)->extend(AuditPages::class);   // in a service provider
```

A `ModuleExtension` decides which modules it `extends()` and returns a new Module from `extend()`. Extensions:

- run in order (configuration, then runtime);
- may add resources, pages, actions, relations or requirements;
- must keep the module's name;
- have their result validated.

They are trusted code, never named by requests. Pages are indexed by the registry, so rebuild its cache after changing extensions.

## Lifecycle

`ModuleCompiler` reports these stages to every `ModuleLifecycleHook` in `gcego-modules.lifecycle.hooks`:

1. `Validated`
2. `Extended`
3. `Normalized` (with the `ModuleDefinition`)
4. `PagesDerived` (with the page names)

Hooks observe; they cannot change definitions. Later stages (routes, page requests, authorization) belong to gcego/pages and have their own hooks (`PageLifecycleHook`).

## Diagnostics

```php
$report = app(ModuleManager::class)->inspect('academics');
$report->isHealthy(); $report->errors(); $report->warnings(); $report->checks(); $report->toArray();
```

| Check | Level | Meaning |
| --- | --- | --- |
| `module.invalid` | error | the module does not compile |
| `resource.empty` | warning | no Table, Form with Create/Edit, or Infolist: no pages |
| `resource.form` | warning | a Form without the Create or Edit action |
| `authorization.policy` | warning | the model has no policy: everyone is denied |
| `authorization.requirements` | warning | no requirements with `authorize_by_default` off |
| `relation.parent` / `relation.missing` / `relation.invalid` / `relation.model` | error | the relationship cannot work |
| `relation.attach` | error | Attach or Detach on a relationship that isn't BelongsToMany |
| `page.missing` | error | a derived page is not in the registry (stale cache) |
| `page.*` | as reported | the gcego/pages inspector's findings for each page (route conflicts, renderers, styling…) |
| `performance.resources` / `performance.relations` | warning | very large modules |
| `navigation.empty` | warning | in the navigation with nothing to link |

Inspection never queries or opens a page.
