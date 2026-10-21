# gcego/blueprints

A **Blueprint** is the declarative definition of one GCEGO Admin Studio
feature, such as subject management. It names the feature, and it
references the classes that implement it: model, table, form, infolist,
pages, actions, relations and widgets.

```php
return Blueprint::define(
    Name::make('subjects'),
    Title::make('Subject Management'),
    Model::make(Subject::class),
    Table::make(SubjectsTable::class),
    Pages::make([ListSubjects::class, CreateSubject::class]),
);
```

Blueprints store **references** and never implement engines. A blueprint does
not render, query, route or authorize anything, and defining one never loads
the classes it names. The tables, forms, pages, actions and widgets packages
read the references and build their own objects.

## Installation

```bash
composer require gcego/blueprints
```

| Requirement | Why |
| --- | --- |
| PHP 8.3+ | |
| `gcego/core` ^1.0 | Framework integration. The service provider is a GCEGO package (`gcego/blueprints`, capability `blueprints`). |
| `gcego/registry` ^1.1 | Discovery, registration and resolution of blueprint modules. 1.1 is required: it loads class entrypoints once per process and attributes shared-folder files by kind. |
| `illuminate/contracts`, `illuminate/support` ^13 | The Laravel service provider. |

The package does **not** depend on `gcego/components`, `gcego/tables`,
`gcego/forms`, `gcego/infolists`, `gcego/actions`, `gcego/pages`,
`gcego/widgets` or `gcego/runtime`. A blueprint can reference their classes
whether or not those packages are installed.

`BlueprintsServiceProvider` is auto-discovered. Publish the config (optional) with:

```bash
php artisan vendor:publish --tag=gcego-blueprints-config
```

## Defining a blueprint

`Blueprint::define(BlueprintDefinition ...$definitions): Blueprint` accepts
typed definition objects:

```php
<?php

namespace App\GCEGo\Modules\Academics\Subjects;

use App\Models\Subject;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\{
    Name, Title, Description, Model, Table, Form, Infolist, Pages, Actions, Relations, Widgets
};
use Gcego\Blueprints\Registry\{BlueprintProvider, ProvidesBlueprint};

use App\GCEGo\Modules\Academics\Subjects\Pages\{ListSubjects, CreateSubject, EditSubject, ViewSubject};
use App\GCEGo\Modules\Academics\Subjects\Tables\SubjectsTable;
use App\GCEGo\Modules\Academics\Subjects\Forms\SubjectForm;
use App\GCEGo\Modules\Academics\Subjects\Infolists\SubjectInfolist;
use App\GCEGo\Modules\Academics\Subjects\Actions\{DeleteSubject, ExportSubjects};
use App\GCEGo\Modules\Academics\Subjects\Relations\{TopicsRelation, SyllabiRelation};
use App\GCEGo\Modules\Academics\Subjects\Widgets\{SubjectsOverview, SubjectsStatistics};

final class Subjects implements BlueprintProvider
{
    use ProvidesBlueprint;

    public function blueprint(): Blueprint
    {
        return Blueprint::define(
            Name::make('subjects'),
            Title::make('Subject Management'),
            Description::make('Manage academic subjects'),
            Model::make(Subject::class),

            Table::make(SubjectsTable::class),
            Form::make(SubjectForm::class),
            Infolist::make(SubjectInfolist::class),

            Pages::make([
                ListSubjects::class,
                CreateSubject::class,
                EditSubject::class,
                ViewSubject::class,
            ]),

            Actions::make([
                DeleteSubject::class,
                ExportSubjects::class,
            ]),

            Relations::make([
                TopicsRelation::class,
                SyllabiRelation::class,
            ]),

            Widgets::make([
                SubjectsOverview::class,
                SubjectsStatistics::class,
            ]),
        );
    }
}
```

The file needs no `return` statement: it is a *class entrypoint*. The
registry finds the single `DefinitionProvider` class it declares. Ending it
with `return Subjects::class;` also works. The package test suite discovers
this exact module through the registry, including after Composer has already
autoloaded the class (`tests/Integration/SubjectsModuleTest.php`).

## Definition classes

All definitions live in `Gcego\Blueprints\Definitions` and are immutable
(`readonly`). Each one validates and normalizes its value in `make()`.

> **See the [definitions guide](docs/definitions.md)** for every definition
> with examples, the base classes, and where definitions that aren't built in
> (navigation, permissions, exports, routes, ...) go instead.

The built-in set is deliberately small. A definition is built in only if a
GCEGO engine reads it to build the feature.

| Definition | Syntax | Value | Read by |
| --- | --- | --- | --- |
| `Name` (**required**) | `Name::make('subjects')` | lowercase identifier | registry identity |
| `Title` | `Title::make('Subject Management')` | string; derived from the name when omitted | every UI |
| `Description` | `Description::make('Manage academic subjects')` | string | every UI |
| `Icon` | `Icon::make('academic-cap')` | icon identifier | navigation, pages |
| `Model` | `Model::make(Subject::class)` | class name | data, tables, forms |
| `Policy` | `Policy::make(SubjectPolicy::class)` | class name; Laravel's Gate resolves one when omitted | authorization |
| `Table` | `Table::make(SubjectsTable::class)` | class name | tables |
| `Form` | `Form::make(SubjectForm::class)` | class name | forms |
| `Infolist` | `Infolist::make(SubjectInfolist::class)` | class name | infolists |
| `Pages` | `Pages::make([ListSubjects::class, ...])` | list of class names | pages |
| `Actions` | `Actions::make([DeleteSubject::class, ...])` | list of class names | actions |
| `BulkActions` | `BulkActions::make([DeleteSubjects::class, ...])` | list of class names (key `bulk_actions`) | tables, actions |
| `Relations` | `Relations::make([TopicsRelation::class, ...])` | list of class names | pages |
| `Widgets` | `Widgets::make([SubjectsOverview::class, ...])` | list of class names | widgets |
| `Derive` | `Derive::make([SubjectPages::class])` | list of `BlueprintDeriver` classes | registry (opt-in, see below) |

Single references take one class: `Table::make(SubjectsTable::class)`.
Collections always take **one array**, `Pages::make([...])`, never variadic
arguments. Declare each definition once, and put every page in a single
`Pages::make([...])` call.

What is **not** built in, and why:

- **Capabilities that other packages own** (navigation, permissions, panels,
  themes, settings, tenancy, search, audit, ...) are registered by those
  packages, with values shaped for them. See
  [Extending](#extending-new-definition-types). The core leaves those keys
  free, so for example gcego/navigation can register `navigation`.
- **Parts of an engine's own class** (columns, fields, entries, filters,
  layouts) live inside the table, form or infolist class that `Table`,
  `Form` and `Infolist` reference. That keeps one source of truth.
- **Laravel application wiring** (routes, controllers, middleware,
  commands, jobs, events, listeners, observers, mailables, notifications)
  stays with Laravel's own registration. A blueprint describes a feature; it
  isn't a container.

## Reading a blueprint

`Blueprint` is a `final readonly` class. Its accessors return predictable
values:

| Accessor | Returns |
| --- | --- |
| `name()` | `string` |
| `title()` | `string`: the Title, else the humanized name (`academic-subjects` → `Academic Subjects`) |
| `slug()` | `string`: the slugified name |
| `description()`, `icon()` | `?string` |
| `model()`, `policy()`, `table()`, `form()`, `infolist()` | `?class-string`, or `null` when not declared |
| `pages()`, `actions()`, `bulkActions()`, `relations()`, `widgets()`, `derivers()` | `list<class-string>` in declaration order; `[]` when not declared |
| `has($key)`, `definition($key)`, `get($key)` | generic access by key; `get()` returns the normalized value, or `null` |
| `find(Navigation::class)` | the definition of that class, typed, or `null` |
| `keys()`, `definitions()` | keys and definition objects, in declaration order |
| `references()` | `array<key, list<class-string>>`: every class reference (keys without any class are omitted) |
| `identity()`, `metadata()`, `toArray()` | identity value object, metadata value object, normalized array |

Blueprints cannot be changed in place. `with(...$definitions)` returns a copy
in which each given definition explicitly replaces the existing definition
with the same key. `without(...$keys)` returns a copy without those keys.
Both copies are validated like `define()`.

## Validation

Validation runs in two phases.

**Structural validation** runs when you define a blueprint and needs only
`gcego/blueprints`. It never autoloads referenced classes.

- `Name` must be a lowercase identifier: letters, digits, `-` and `_`, at
  most 100 characters, starting with a letter. A non-conforming name is
  rejected rather than rewritten: `"Subjects"` fails with the hint
  `Did you mean "subjects"?`.
- `Title` must be non-empty after trimming and at most 200 characters.
- `Description` must be non-empty, at most 1000 characters, and contain no
  control characters other than line breaks and tabs.
- A class reference must be a syntactically valid class name. A leading `\`
  is removed.
- A collection must be a non-empty **list**. Keyed arrays, non-string
  elements and duplicate classes are rejected. Duplicates are matched
  case-insensitively, as PHP matches class names.
- `define()` rejects unsupported definition keys, duplicate keys and missing
  required definitions. It reports all of these problems together in one
  `InvalidBlueprintException`, whose `result()` holds structured
  `BlueprintValidationError`s with codes:
  `blueprint.unsupported_definition`, `blueprint.duplicate_definition` and
  `blueprint.missing_required_definition`.

Invalid values throw `InvalidDefinitionException` from `make()`. The
exception names the definition key, the reason, and usually a hint.

**Integration validation** runs when the registry registers or resolves a
blueprint. The `BlueprintValidator` checks each reference against the
installed `ReferenceRule` adapters:

- `ClassExists`: every reference must be autoloadable. This rule may
  autoload a referenced class; it never instantiates one. It also reports a
  class that extends a class from a package that isn't installed.
- `ImplementsContract(Derive::Key, BlueprintDeriver::class)`: derivers must
  implement `BlueprintDeriver`.
- Rules added by feature packages, for example "`table` references must
  implement the tables contract".

Integration validation cannot be skipped in production.
`gcego-blueprints.validate_references = false` is honored only in the
`local` and `testing` environments; in every other environment it is
ignored and references are always checked. Structural validation always
runs.

**Before executing a reference**, engines take it from the
`ReferenceResolver` instead of the raw accessors. The resolver applies the
full rules regardless of configuration, and whether or not the blueprint
came through the registry:

```php
$table = app(ReferenceResolver::class)->one($blueprint, Table::Key, TableDefinition::class);
$pages = app(ReferenceResolver::class)->all($blueprint, Pages::Key);
```

It throws `InvalidReferenceException`, and the class is never run.

**Configuration is fixed once it is in use**, so every blueprint in a process
is checked the same way:

- `ReferenceRules` locks on the first validation. Adding a rule afterwards
  throws, so add rules from a service provider's `register()` method.
- `DefinitionCatalog` refuses a *required* definition type once any
  blueprint has been defined. Existing blueprints would otherwise become
  invalid without anyone noticing. Optional types can be added at any time.

## Module folders and the registry

### Kind ownership

| Kind | Owner | Entrypoints under `app/GCEGo/Modules` | Identity |
| --- | --- | --- | --- |
| `blueprint` | gcego/blueprints | a file named after its folder: `Academics/Subjects/Subjects.php` | `blueprint:subjects` |
| `module` | gcego/modules | `Module.php` files: `Academics/Module.php` | `module:academics` |

Each package registers its own kind and adds its own discovery roots, so the
two can share one folder. Their entrypoint conventions are disjoint: a folder
named `Module` is never a blueprint entrypoint. The same name can exist under
both kinds, because identities include the kind.

`BlueprintsServiceProvider` registers `BlueprintKind` when the registry's
`DefinitionKinds` is resolved, and adds a `blueprint` root for each path in
`gcego-blueprints.discovery.paths` just before the registry reads its
configuration. Both work in any provider order. A root that
`gcego-registry.discovery.roots` already lists for the same folder and kind
takes precedence. Set `gcego-blueprints.discovery.enabled` to `false` to
manage roots yourself.

The registry's shipped configuration still maps `app/GCEGo/Modules` to
`module` with the folder-name convention, so a blueprint entrypoint matches
both roots. The registry then gives the file to the registered kind whose
contract accepts the loaded object (the blueprint kind), whether or not
gcego/modules is installed.

### Discovery

```text
app/GCEGo/Modules/
└── Academics/
    ├── Module.php            ← gcego/modules entrypoint (if installed)
    └── Subjects/
        ├── Subjects.php      ← blueprint entrypoint: named after its folder
        ├── Pages/            ← supporting classes, never entrypoints
        ├── Tables/
        ├── Forms/
        ├── Infolists/
        ├── Actions/
        ├── Relations/
        └── Widgets/
```

1. Registry discovers `Subjects.php` under the `blueprint` root.
2. The file declares `Subjects`, a `BlueprintProvider`. Registry resolves the
   class through the container, so constructor injection works.
3. `ProvidesBlueprint` implements `definitions()` by yielding `blueprint()`.
4. `BlueprintKind` names the blueprint (`blueprint:subjects`), validates its
   references and indexes scalar metadata (`title`, `slug`, `description`,
   `model`, `table`, `form`, `infolist`, `definitions`).
5. `Registry::get('blueprint:subjects')` returns the `Blueprint`.

Files under `Pages/`, `Tables/` and the other supporting folders are not
entrypoints and are never registered on their own. Editing or adding one
changes the registry fingerprint, so a verified cache rebuilds. Two modules
that declare the same `Name` are a `DuplicateDefinitionException` naming
both files.

Class-based providers also work from `gcego-registry.providers`, and you can
register at runtime with `Registry::register(Blueprint::define(...))`.

### Class entrypoints and long-running processes

An entrypoint that declares a class is included **at most once per PHP
process**. Later loads reuse the first result: `Registry::refresh()`, cache
rebuilds, cache verification, and resolving from the cache. An entrypoint
whose class Composer already autoloaded (for example via `new Subjects`) is
not included again either. So none of these paths can trigger a "cannot
declare class" error.

PHP cannot replace a class that's already declared. If a long-running process
(a queue worker, Octane) sees an entrypoint change after loading it, the
registry throws a `DefinitionLoadException` asking for a restart instead of
serving the old class. Supporting files are ordinary autoloaded classes and
follow the same rule: restart workers on deploy, as Laravel requires anyway.

### Derived definitions (opt-in)

A blueprint derives nothing by default. To have the registry also register
definitions of other kinds, such as one page definition per page, request a
deriver explicitly:

```php
Blueprint::define(
    Name::make('subjects'),
    Pages::make([ListSubjects::class, EditSubject::class]),
    Derive::make([SubjectPages::class]),   // implements BlueprintDeriver
);
```

`Blueprint` implements the registry's `DerivesDefinitions` contract.
Derivers are instantiated without arguments and must be pure. Their output
is indexed next to the blueprint and validated by its own kind. A deriver
that yields a blueprint is rejected, so derivation can never create a second
blueprint identity. Registry's usual duplicate-identity checks apply to
everything else.

## Extending: new definition types

A package that owns a capability registers a typed definition for it from its
service provider's `register()` method. Pick the base class that matches the
shape of the value:

| Base class | Value | Use for |
| --- | --- | --- |
| `OptionsDefinition` + `Option` | typed, validated options | data: `Navigation::make(group: 'Academics', icon: 'book-open', sort: 2)` |
| `IdentifierListDefinition` | distinct lowercase identifiers | names: `Permissions::make(['view', 'create'])`, `Panels::make(['admin'])` |
| `ReferenceDefinition` | one class name | `Exporter::make(SubjectsExporter::class)` |
| `ReferenceCollectionDefinition` | list of class names | `Reports::make([AttendanceReport::class])` |
| `TextDefinition` | one validated string | short text |

```php
namespace Gcego\Navigation\Blueprints;

use Gcego\Blueprints\Definitions\{Option, OptionsDefinition};

final readonly class Navigation extends OptionsDefinition
{
    public const string Key = 'navigation';

    public static function definitionKey(): string
    {
        return self::Key;
    }

    public static function schema(): array
    {
        return [
            'group' => Option::text(required: true),
            'icon' => Option::icon(),
            'sort' => Option::integer(default: 0, min: 0),
            'badge' => Option::className(),   // a reference, checked like Table::make()
        ];
    }

    public static function make(string $group, ?string $icon = null, int $sort = 0, ?string $badge = null): self
    {
        return self::fromOptions(['group' => $group, 'icon' => $icon, 'sort' => $sort, 'badge' => $badge]);
    }
}

// NavigationServiceProvider::register()
DefinitionCatalog::register(Navigation::class);
```

Option types: `text`, `identifier`, `identifiers`, `icon`, `integer` (with
optional `min` and `max`), `boolean` and `className`. Unknown options are
rejected. A `null` option is treated as absent: its default applies, or it
is reported if required. The value lists every option in schema order.

- A key belongs to exactly one class. Claiming a taken key (`table`) throws
  `DefinitionRegistrationException`, and so does an invalid key or a class
  that isn't a definition. Required types (`register(X::class, required:
  true)`) are accepted only before the first blueprint is defined.
- `define()` rejects keys that aren't registered. A blueprint that uses
  `Navigation::make()` while gcego/navigation isn't installed fails with
  "unsupported definition key", and is never silently ignored.
- Class options and reference definitions implement `ReferencesClasses`.
  Their classes are checked by the reference rules and the
  `ReferenceResolver`, like the built-in references.
- Consumers read values with `$blueprint->find(Navigation::class)?->option('group')`.

Working examples of `Navigation`, `Permissions`, `Panels` and `Theme` live in
`tests/Fixtures/Extensions`, registered by `ExtensionsServiceProvider` and
covered end to end through the registry.

To check references against a feature contract, add a reference rule from
the feature package. The contract is a string, so this package never imports
optional packages:

```php
$this->callAfterResolving(ReferenceRules::class, fn (ReferenceRules $rules) => $rules->add(
    new ImplementsContract(Table::Key, \Gcego\Tables\Contracts\TableDefinition::class),
));
```

## Migrating from the component-based API

Before this release, `Blueprint` extended `Gcego\Components\Component` and
composed component instances by role. That API has been removed with the
`gcego/components` dependency.

| Before | Now |
| --- | --- |
| `new Blueprint('subjects', new SubjectsTable, ...)` | `Blueprint::define(Name::make('subjects'), Table::make(SubjectsTable::class), ...)` |
| `new Property(Blueprint::Title, '...')` | `Title::make('...')` |
| `new Property(Blueprint::Description, '...')` | `Description::make('...')` |
| `Blueprint::Icon`, `Blueprint::Slug` properties | removed; the slug is always derived from the name |
| parts implementing `BlueprintPart` / `PartContainer` | class references through typed definitions |
| `BlueprintRole`, `part()`, `requirePart()`, `parts()`, `roles()` | `table()`, `form()`, `pages()`, ... and `references()` |
| `withPart()`, `withoutRole()` | `with()`, `without()` |
| subclassing `Blueprint` with a `CompositionPolicy` | `DefinitionCatalog::register(..., required: true)` |
| `BlueprintNormalizer`, `BlueprintInspector` | `toArray()`, `metadata()`, registry descriptors |
| `BlueprintValidator` (component schema) | `BlueprintValidator` (reference rules) |

These parts still work unchanged: `BlueprintCollection`, `BlueprintResolver`,
`BlueprintIdentity`, `Naming`, `InvalidBlueprintException::result()` and
`BlueprintValidationResult`.

## Development

```bash
composer test       # Pest
composer analyse    # PHPStan (level max, Larastan)
composer format     # Pint
composer benchmark
```

More detail: [definitions guide](docs/definitions.md), [architecture](docs/architecture.md),
[validation](docs/validation.md), [extension guide](docs/extension-guide.md),
[API reference](docs/api-reference.md).
