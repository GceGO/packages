# Definitions guide

Everything you can put in `Blueprint::define()`, what each definition is for,
and what to do when the definition you want isn't built in.

The files in `src/Definitions` fall into three groups:

1. **[Built-in definitions](#1-built-in-definitions)** (15): what you write in
   `Blueprint::define()` every day.
2. **[Base classes](#2-base-classes-for-your-own-definitions)** (5): what you
   extend to add a definition of your own.
3. **[Internal machinery](#3-internal-machinery)** (2): the catalog that
   decides which definitions `define()` accepts.

## 1. Built-in definitions

| Definition | Syntax | Key | Read back with | Purpose |
| --- | --- | --- | --- | --- |
| `Name` | `Name::make('subjects')` | `name` | `name()` | **Required.** The identity: `blueprint:subjects` in the registry |
| `Title` | `Title::make('Subject Management')` | `title` | `title()` | Display title; derived from the name when omitted (`academic-subjects` → `Academic Subjects`) |
| `Description` | `Description::make('Manage academic subjects')` | `description` | `description()` | Descriptive text |
| `Icon` | `Icon::make('academic-cap')` | `icon` | `icon()` | Icon for menus and page headers (`academic-cap`, `lucide:book-open`) |
| `Model` | `Model::make(Subject::class)` | `model` | `model()` | The record class the feature manages |
| `Policy` | `Policy::make(SubjectPolicy::class)` | `policy` | `policy()` | Authorization policy for the records (see below) |
| `Table` | `Table::make(SubjectsTable::class)` | `table` | `table()` | The list or table |
| `Form` | `Form::make(SubjectForm::class)` | `form` | `form()` | The create and edit form |
| `Infolist` | `Infolist::make(SubjectInfolist::class)` | `infolist` | `infolist()` | The read-only detail view |
| `Pages` | `Pages::make([ListSubjects::class, ...])` | `pages` | `pages()` | The feature's pages, in order |
| `Actions` | `Actions::make([DeleteSubject::class, ...])` | `actions` | `actions()` | Actions on one record or on the page |
| `BulkActions` | `BulkActions::make([DeleteSubjects::class, ...])` | `bulk_actions` | `bulkActions()` | Actions on selected rows (see below) |
| `Relations` | `Relations::make([TopicsRelation::class, ...])` | `relations` | `relations()` | Related-record managers |
| `Widgets` | `Widgets::make([SubjectsOverview::class, ...])` | `widgets` | `widgets()` | Stats, charts and other dashboard pieces |
| `Derive` | `Derive::make([SubjectPages::class])` | `derive` | `derivers()` | Opt-in: Registry also registers what these derivers produce, such as one page definition per page |

Rules that apply to all of them:

- Only `Name` is required.
- Each definition may appear **once**. Declaring `Table` twice is an error,
  never a silent overwrite.
- Single references take one class: `Table::make(SubjectsTable::class)`.
- Collections always take **one array**, never variadic arguments:
  `Pages::make([A::class, B::class])`. Put every page in a single
  `Pages::make([...])` call.
- Classes are stored by name. Defining a blueprint never loads or runs them.
  Registry checks they exist when it registers the blueprint.
- Absent definitions read back as `null` (single) or `[]` (collections).

### Policy

Optional. Without it, the engines use the policy Laravel's Gate resolves for
the model (`Subject` → `SubjectPolicy`). Declare `Policy` only when the
policy is not the one Laravel would find on its own.

### Actions vs BulkActions

`Actions` work on one record or on the page (delete this subject, create a
subject). `BulkActions` work on a selection of records, so the table engine
offers them only when rows are selected. An export can be either: export
this subject (`Actions`), or export the selected subjects (`BulkActions`).

### Complete example

```php
<?php

namespace App\GCEGo\Modules\Academics\Subjects;

use App\Models\Subject;
use App\Policies\SubjectPolicy;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Definitions\{
    Name,
    Title,
    Description,
    Icon,
    Model,
    Policy,
    Table,
    Form,
    Infolist,
    Pages,
    Actions,
    BulkActions,
    Relations,
    Widgets
};
use Gcego\Blueprints\Registry\{BlueprintProvider, ProvidesBlueprint};

use App\GCEGo\Modules\Academics\Subjects\Pages\{ListSubjects, CreateSubject, EditSubject, ViewSubject};
use App\GCEGo\Modules\Academics\Subjects\Tables\SubjectsTable;
use App\GCEGo\Modules\Academics\Subjects\Forms\SubjectForm;
use App\GCEGo\Modules\Academics\Subjects\Infolists\SubjectInfolist;
use App\GCEGo\Modules\Academics\Subjects\Actions\{DeleteSubject, DeleteSubjects, ExportSubjects};
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
            Icon::make('academic-cap'),
            Model::make(Subject::class),
            Policy::make(SubjectPolicy::class),

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
            ]),

            BulkActions::make([
                DeleteSubjects::class,
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

### Reading definitions back

```php
$blueprint->name();          // 'subjects'
$blueprint->icon();          // 'academic-cap'
$blueprint->policy();        // SubjectPolicy::class
$blueprint->table();         // SubjectsTable::class, or null
$blueprint->bulkActions();   // [DeleteSubjects::class, ExportSubjects::class], or []
$blueprint->get('pages');    // the same list as pages(), by key
$blueprint->keys();          // ['name', 'title', ...] in declaration order
```

Engines that are about to run a referenced class take it from the
`ReferenceResolver` instead, which checks it first (see
[validation](validation.md#execution-guard)):

```php
app(ReferenceResolver::class)->one($blueprint, Table::Key, TableDefinition::class);
```

## 2. Base classes for your own definitions

You don't call these in `define()`. You extend them to create a new
definition.

| File | Extend it when the value is... | Built-ins that use it / example |
| --- | --- | --- |
| `TextDefinition.php` | one validated string | `Name`, `Title`, `Description`, `Icon` |
| `ReferenceDefinition.php` | one class | `Model`, `Policy`, `Table`, `Form`, `Infolist` |
| `ReferenceCollectionDefinition.php` | a list of classes | `Pages`, `Actions`, `BulkActions`, `Relations`, `Widgets`, `Derive` |
| `OptionsDefinition.php` + `Option.php` | data with several fields | `Navigation::make(group: 'Academics', icon: 'book-open', sort: 2)` |
| `IdentifierListDefinition.php` | a list of names | `Permissions::make(['view', 'create'])` |

How to write one is covered step by step in the
[extension guide](extension-guide.md). Working examples live in
`tests/Fixtures/Extensions`.

## 3. Internal machinery

| File | Role |
| --- | --- |
| `DefinitionCatalog.php` | The list of accepted keys. `Blueprint::define()` rejects any definition whose key isn't in it. Add yours with `DefinitionCatalog::register(YourDefinition::class)`. |
| `DefinitionType.php` | One catalog entry: key, class, and whether it's required. |

You never create these directly. You only call `DefinitionCatalog::register()`.

## Definitions that are not built in

Some definitions were deliberately left out of the core. Here is where each
one goes instead:

| You want... | Use instead |
| --- | --- |
| `Navigation`, `Permissions`, `Panels`, `Theme`, `Settings`, `Tenancy`, `Search`, `Audit`, `Cache`, `Translations` | Your own definition, [registered by your app](#adding-one-in-your-app). Eventually gcego/navigation, gcego/security, gcego/panels and the other owning packages ship the official ones. |
| `Tables`, `Forms`, `Infolists` | One `Table`, `Form` and `Infolist` per feature. A second table usually means a second blueprint, or a `Relations` entry for related records. |
| `Columns`, `Fields`, `Entries`, `Filters`, `Scopes` | Inside the class you already reference: columns and filters in `SubjectsTable`, fields in `SubjectForm`, entries in `SubjectInfolist`. |
| `Layout`, `Layouts`, `Dashboards`, `Components`, `Charts`, `Metrics`, `Reports` | `Widgets::make([...])` for dashboard pieces, `Pages::make([...])` for dashboard or report pages. |
| `Exports`, `Imports` | `Actions::make([...])`, or `BulkActions::make([...])` for selected rows. |
| `DataSource`, `Repository`, `Query`, `Schema` | `Model::make(Subject::class)`. Query customisation belongs in the table class. |
| `Routes`, `Controllers`, `Middleware`, `Commands`, `Jobs`, `Events`, `Listeners`, `Observers`, `Mailables`, `Notifications`, `Webhooks`, `Validators`, `Rules`, `Media`, `Uploads`, `Integrations`, `Workflows`, `Resource` | Laravel's normal registration: `routes/*.php`, `bootstrap/app.php`, event discovery, `#[ObservedBy]`, form requests, and so on. A blueprint describes a feature; it doesn't register application wiring. |

Using a definition that isn't registered fails loudly:

```text
Argument 2 (App\Blueprints\Navigation) declares the unsupported definition key "app.navigation".
```

### Adding one in your app

**1. Write the definition** with the base class that fits the value:

```php
namespace App\Blueprints;

use Gcego\Blueprints\Definitions\{IdentifierListDefinition, Option, OptionsDefinition};

final readonly class Navigation extends OptionsDefinition
{
    public const string Key = 'app.navigation';

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
        ];
    }

    public static function make(string $group, ?string $icon = null, int $sort = 0): self
    {
        return self::fromOptions(['group' => $group, 'icon' => $icon, 'sort' => $sort]);
    }
}

final readonly class Permissions extends IdentifierListDefinition
{
    public const string Key = 'app.permissions';

    public static function definitionKey(): string
    {
        return self::Key;
    }
}
```

**2. Register it** in `AppServiceProvider::register()`, before any blueprint
is defined:

```php
use Gcego\Blueprints\Definitions\DefinitionCatalog;

DefinitionCatalog::register(\App\Blueprints\Navigation::class);
DefinitionCatalog::register(\App\Blueprints\Permissions::class);
```

**3. Use it** like a built-in, and read it back with `find()` or `get()`:

```php
Blueprint::define(
    Name::make('subjects'),
    Navigation::make('Academics', icon: 'book-open', sort: 2),
    Permissions::make(['view', 'create', 'subjects.export']),
);

$blueprint->find(Navigation::class)?->option('group');   // 'Academics'
$blueprint->get('app.permissions');                       // ['view', 'create', 'subjects.export']
```

Remember:

- **Prefix app keys with `app.`** (`app.navigation`, not `navigation`), so they
  never collide with the official definition a GCEGO package registers later.
  Moving over is then a find-and-replace.
- **Storing a definition does nothing on its own.** Until the owning package
  reads it, your own code (a menu builder, a permission seeder) must read the
  value with `find()` or `get()`.
- **Register required definitions only in `register()`.** Once a blueprint
  exists, `register(X::class, required: true)` throws.
