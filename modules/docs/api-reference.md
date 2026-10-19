# API reference

## Definitions

| Class | Notes |
| --- | --- |
| `Module` | Component, `DerivesDefinitions`. `moduleName()`, `title()`, `resources()`, `resource($name)`, `pages()`, `requirements()`, `navigation()`, `derivedDefinitions()`, `problems()` |
| `Resource` | `resourceName()`, `modelClass()`, `source()`, `table()`, `form()`, `infolist()`, `actions()`, `action($name)`, `recordActions()`, `operations($defaults)`, `relations()`, `pages()`, `requirements()`, `permissions()` |
| `Relation` | `relationship()`, `targetResource()`, plus the shared record-set accessors |
| `Operation` | `Index`, `Create`, `View`, `Edit`: `requiredRole()`, `isRecordPage()`, `ability()` |
| `Relationships\Attach`, `Relationships\Detach` | BelongsToMany actions |

## Compiled definitions

| Class | Notes |
| --- | --- |
| `Definitions\ModuleDefinition` | `name`, `title`, `slug`, `navigation…`, `requirements`, `resources`, `pages`, `resource()`, `pageMap()` |
| `Definitions\ResourceDefinition` | `name`, `pageBase`, `path`, labels, `model`, `source`, `table`, `form`, `infolist`, `actions`, `operations`, `requirements`, `permissions`, `relations`, `pages`; `pageName($op)`, `routeName($op)`, `pagePath($op)`, `exposes($op)`, `identity()` |
| `Definitions\RelationDefinition` | the same, plus `resource`, `relationship`, `parentSource`, `parentRecordKey`, `reference` |

## Services

| Class | Methods |
| --- | --- |
| `ModuleManager` | `names()`, `has()`, `module()`, `definition()`, `modules()`, `resource()`, `relation()`, `page()`, `url()`, `locate()`, `current()`, `currentModule()`, `register()`, `extend()`, `inspect()`, `inspectAll()`, `pageIdentity()` |
| `Compilation\ModuleCompiler` | `compile(Module)`, `pages(Module)` |
| `Pages\ResourcePageFactory` | `pages(ModuleDefinition)`, `page($set, $operation)` |
| `Relationships\ParentRecords` (scoped) | `key()`, `inspect()`, `resolve()`, `relationship()`, `constrain()` |
| `Relationships\ParentScope` | gcego/data `QueryConstraint` |
| `Relationships\ParentAccess` | gcego/security `Requirement` |
| `Relationships\RelatedRecords` (scoped) | `create()`, `attach()`, `detach()` |
| `Context\ModuleContextResolver` (scoped) | `module(): ?ModuleContext`, `resource(): ?ResourceContext` |
| `Navigation\ModuleNavigationContributor` | gcego/navigation `NavigationContributor` |
| `Diagnostics\ModuleInspector` | `inspect($module): ModuleReport` |
| `Registry\ModuleKind` | gcego/registry `DefinitionKind` (`module`) |

## Contracts

`Contracts\ModuleExtension`, `Contracts\ModuleLifecycleHook`.

## Configuration

`gcego-modules`:

| Section | Keys |
| --- | --- |
| `discovery` | `enabled`, `paths`, `entrypoint` |
| `resources` | `auto_pages`, `default_operations` |
| `navigation` | `auto`, `target` |
| `relationships` | `enabled` |
| `security` | `authorize_by_default` |
| top level | `extensions` |
| `lifecycle` | `hooks` |
