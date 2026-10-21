# gcego/modules

Declarative administrative modules for GCEGO Admin Studio. Declare a module and its resources once; gcego/modules turns each resource into complete, authorized gcego/pages pages:

- **CRUD pages:** index, create, view and edit;
- **relationship pages:** for related records under a parent;
- **routes, navigation and diagnostics.**

It is an orchestration layer. Pages, routing and record lookup are gcego/pages'. Lists, forms, details and actions belong to gcego/tables, gcego/forms, gcego/infolists and gcego/actions, data to gcego/data, authorization and tenancy to gcego/security, and menus to gcego/navigation. A module adds no second engine for any of them.

```php
// app/GCEGo/Modules/Academics/Module.php
return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),

    new Resource(
        new Source(Subject::class),
        new Table(new Text('name', 'Subject Name'), new Text('code', 'Code')),
        new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')),
        new Infolist(new Entries\Text('name', 'Subject Name')),
        new Create, new Edit, new Delete,
    ),
);
```

This gives you `/admin/subjects`, `/admin/subjects/create`, `/admin/subjects/{record}` and `/admin/subjects/{record}/edit` (routes `gcego.academics.subjects.index`, `.create`, `.view`, `.edit`). Each page is authorized by the `Subject` policy and scoped to the current tenant. Saving and deleting go through gcego/actions.

## Contents

- [Installation](#installation)
- [Examples](#examples)
- [What becomes a page](#what-becomes-a-page)
- [Routes and names](#routes-and-names)
- [Authorization and tenancy](#authorization-and-tenancy)
- [Relationships](#relationships)
- [Navigation](#navigation)
- [Customizing](#customizing)
- [Extensions and lifecycle](#extensions-and-lifecycle)
- [Context, diagnostics and the manager](#context-diagnostics-and-the-manager)
- [Security](#security)
- [Testing](#testing)

## Installation

```bash
composer require gcego/modules
php artisan vendor:publish --tag=gcego-modules-config   # optional
```

The provider is auto-discovered and needs the first sixteen GCEGO packages (installed by Composer).

Modules are discovered by gcego/registry from `app/GCEGo/Modules`: one `Module.php` per folder, searched recursively (`Academics/Module.php`). Sibling files (`Academics/Subjects.php`) are supporting code you `require` from it. The registry caches the index (`php artisan optimize`) and loads modules lazily.

## Examples

### A minimal module

<!-- example:minimal -->
```php
use App\Models\Subject;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;

return new Module(
    new Resource(Subject::class),
);
```

The module takes its only resource's name (`subjects`), and the resource takes its model's (`Subject` becomes `subjects`). It exposes no pages: nothing in it says which columns, fields or operations are safe. The inspector reports that the resource exposes nothing.

### Several resources

<!-- example:multiple -->
```php
use App\Models\Subject;
use App\Models\Topic;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),

    new Resource(
        new Source(Subject::class),
        new Table(new Text('name', 'Subject Name')),
    ),

    new Resource(
        new Source(Topic::class),
        new Table(new Text('name', 'Topic')),
    ),
);
```

Two list pages: `/admin/subjects` and `/admin/topics`.

### A resource with a form

<!-- example:students -->
```php
use App\Models\Student;
use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Modules\Module;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'students'),

    new Resource(
        new Source(Student::class),

        new Table(
            new Text('name', 'Student Name'),
        ),

        new Form(
            new TextInput('name', 'Student Name', new Property(TextInput::Required, true)),
        ),

        new Create,
        new Edit,
    ),
);
```

A Form becomes the create page with `Create`, and the edit page with `Edit`. A Form without either action (and without them in `Resource::Operations`) is unused, and the inspector says so.

### Relationships and a custom page

<!-- example:complete -->
```php
use App\Models\Subject;
use App\Models\Topic;
use Gcego\Actions\Create;
use Gcego\Actions\Delete;
use Gcego\Actions\Edit;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Section;
use Gcego\Modules\Module;
use Gcego\Modules\Relation;
use Gcego\Modules\Resource;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academic Management'),
    new Property(Module::Navigation, true),

    new Resource(
        new Source(Subject::class),
        new Table(new Text('name', 'Subject Name'), new Text('code', 'Code')),
        new Form(new TextInput('name', 'Subject Name'), new TextInput('code', 'Subject Code')),
        new Infolist(new TextEntry('name', 'Subject Name'), new TextEntry('code', 'Subject Code')),
        new Create,
        new Edit,
        new Delete,

        new Relation('topics', new Property(Relation::Resource, 'topics')),
    ),

    new Resource(
        new Source(Topic::class),
        new Table(new Text('name', 'Topic')),
        new Form(new TextInput('name', 'Topic')),
        new Infolist(new TextEntry('name', 'Topic')),
        new Create,
        new Edit,
    ),

    new DashboardPage(
        new Property(DashboardPage::Name, 'overview'),
        new Property(DashboardPage::Title, 'Academic Overview'),
        new Authenticated,

        new Section('Summary', new Content('Subjects and topics of your institution.')),
    ),
);
```

`/admin/subjects/7/topics` lists only subject 7's topics, and creating one there links it to subject 7. The overview page lives at `/admin/academics/overview`, and the module is a navigation group.

These examples are tested (`tests/Unit/DocumentedExamplesTest.php`).

## What becomes a page

A resource gets an operation's page only when it declares what that page coordinates:

| Operation | Needs | Page | URL |
| --- | --- | --- | --- |
| `index` | a `Table` | `IndexPage` | `/admin/subjects` |
| `create` | a `Form` and the `Create` action | `CreatePage` | `/admin/subjects/create` |
| `view` | an `Infolist` | `ViewPage` | `/admin/subjects/{record}` |
| `edit` | a `Form` and the `Edit` action | `EditPage` | `/admin/subjects/{record}/edit` |

Other actions are never implied, and appear only where you declare them:

- `Delete`, `Restore` and `ForceDelete` on rows and record pages;
- bulk actions and custom actions.

The index page gets a "New" link when there is a create page, and a View row action when there is a view page. The view page links to the edit page and to each relationship.

### Resource properties

| Property | Use |
| --- | --- |
| `Name` | Identity; the model's plural by default |
| `Model` | Shorthand `new Resource(Subject::class)`; a `Source` can be given instead |
| `Label`, `PluralLabel` | Labels |
| `Slug` | URL segment |
| `Icon` | Icon |
| `RecordTitle`, `RecordKey` | The record's label attribute and key shape (`id`, `uuid`, `ulid`, `slug`, `key`) |
| `Operations` | Restricts the operations, or enables `create`/`edit` without declaring the action |
| `Permissions` | gcego/security permissions per operation |
| `Navigation`, `NavigationLabel`, `NavigationOrder` | Navigation settings |

### Module properties

| Property | Use |
| --- | --- |
| `Name`, `Title`, `Description`, `Icon` | Identity and labels |
| `Slug` | URL prefix of every page of the module |
| `Navigation`, `NavigationGroup`, `NavigationOrder` | Navigation settings |
| `Version` | Metadata only |

Requirement nodes on a module or resource apply to all its pages.

## Routes and names

Generated pages are ordinary `page` definitions (identity `page:academics-subjects-edit`), so gcego/pages registers their routes from the registry index at boot. Routes are cacheable, and a name or URL conflict throws at boot. Names are deterministic:

| Page | Page name | Route |
| --- | --- | --- |
| list | `academics.subjects` | `gcego.academics.subjects.index` |
| create | `academics.subjects.create` | `gcego.academics.subjects.create` |
| view | `academics.subjects.view` | `gcego.academics.subjects.view` |
| edit | `academics.subjects.edit` | `gcego.academics.subjects.edit` |
| relation list | `academics.subjects.topics` | `gcego.academics.subjects.topics.index` |

The prefix (`/admin`, `gcego.`) is gcego/pages'; future panels register these routes under their own prefix. When a resource has the module's name, its pages drop the repetition (`subjects`, not `subjects.subjects`).

```php
app(ModuleManager::class)->url('academics', 'subjects', Operation::Edit, 7);                          // "/admin/subjects/7/edit"
app(ModuleManager::class)->url('academics', 'subjects', Operation::Index, parameters: ['parent' => 7], relation: 'topics');
```

## Authorization and tenancy

Each generated page requires:

- the module's and the resource's requirement nodes;
- the operation's permission (`Resource::Permissions`);
- by default (`security.authorize_by_default`), the model's policy:
  - `viewAny` for the list;
  - `create` for the create page;
  - record pages need a signed-in user, and gcego/pages authorizes `view` or `update` on the record itself.

A model without a policy is denied, so a resource never grants CRUD on its own. With `authorize_by_default` off, only your declarations apply (and gcego/pages' default, `deny`).

Records are always read through the resource's Source, so tenant and security scopes apply: another tenant's record is 404. gcego/actions re-resolves and re-authorizes on every run (delete needs `delete`). gcego/forms writes only declared, writable fields, so a forged `institution_id` or hidden attribute is rejected. Everything is checked again on every Livewire update.

## Relationships

A `Relation` manages the records related to one record of a resource, through an Eloquent relationship of its model:

```php
new Relation('topics', new Property(Relation::Resource, 'topics'))                // HasMany, reusing the "topics" resource
new Relation('tags', new Property(Relation::Resource, 'tags'), new Attach, new Detach)   // BelongsToMany
new Relation('notes', new Property(Relation::Model, Note::class), new Table(...))       // its own definitions
```

| Page | URL |
| --- | --- |
| list | `/admin/subjects/{parent}/topics` |
| create | `/admin/subjects/{parent}/topics/create` |
| view | `/admin/subjects/{parent}/topics/{record}` |
| edit | `/admin/subjects/{parent}/topics/{record}/edit` |

- **The parent is checked:** it is looked up through the parent resource's Source (scoped) and authorized: `view` to read, `update` to create, attach or detach. Unknown or out-of-scope parents are 404; denied ones are 403.
- **The records are constrained:** they are limited to the parent's relationship on every query, lookup and action (`ParentScope`). `/admin/subjects/2/topics/1` is a 404 when topic 1 belongs to subject 1.
- **Links are set on the server:** created records are linked to the parent here (the foreign key or pivot is never taken from the form). `Attach` looks the chosen record up in scope before attaching it, and `Detach` only detaches records of this parent.
- **Related records keep their own policies.**

See [docs/relationships.md](docs/relationships.md) for how the parent is found on Livewire updates and for the limits.

## Navigation

Modules opt in with `Module::Navigation` (or every module, with `navigation.auto`). Each becomes a group in `navigation.target` (`admin`), containing:

- its resources' list pages (unless `Resource::Navigation` is `false`);
- its own pages without parameters.

Items carry their page's requirements (shown only to users who may open the page) and are active on all of the resource's pages, its relationship pages included.

## Customizing

- **Labels, slugs, navigation:** use the resource properties.
- **Replacing a generated page:** declare a page named after the operation inside the resource. For example, `new IndexPage(new Property(Page::Name, 'index'), …)` replaces the list. It keeps the generated name, path and route, and gets the resource's Source and requirements.
- **Extra resource pages:** `new Page(new Property(Page::Name, 'syllabus'), new Property(Page::Record, true), …)` becomes `academics.subjects.syllabus` at `/admin/subjects/{record}/syllabus`.
- **Module pages:** pages declared in the module (dashboards, reports) are named `academics.<name>` and get the module's requirements.
- **Restricting operations:** use `Resource::Operations`.

## Extensions and lifecycle

Trusted extensions add to modules. They are listed in `gcego-modules.extensions` or added with `ModuleManager::extend()`:

```php
final class AddGlossary implements ModuleExtension
{
    public function extends(string $module): bool { return $module === 'academics'; }

    public function extend(Module $module): Module
    {
        return $module->withAppendedChildren(new Page(new Property(Page::Name, 'glossary'), new Authenticated, new Content('…')));
    }
}
```

They run in order, must keep the module's name, and their result is validated.

Compilation goes through these stages:

1. `Validated`
2. `Extended`
3. `Normalized`
4. `PagesDerived`

Each is reported to `lifecycle.hooks` (`ModuleLifecycleHook`). Discovery, routing and per-request authorization are gcego/registry's and gcego/pages'. Rebuild the registry cache after changing extensions.

## Context, diagnostics and the manager

```php
$modules = app(ModuleManager::class);

$modules->names();                                   // ["academics", "students"]
$modules->module('academics');                       // ModuleDefinition (resources, pages, metadata)
$modules->resource('academics', 'subjects');         // ResourceDefinition (operations, relations, …)
$modules->current();                                 // ResourceContext of this request: resource, relation, operation, record, parent
$modules->inspect('academics')->diagnostics;         // see below
$modules->register($module);                         // runtime registration, pages included
```

The inspector reports:

- invalid definitions;
- resources that expose nothing;
- unused forms;
- models without policies;
- relationships that don't exist;
- Attach or Detach on a relationship that isn't BelongsToMany;
- pages missing from the registry (stale cache);
- the page inspector's findings for every page (route conflicts, renderers…);
- size warnings.

It never queries.

## Security

- **Definitions are trusted code:** they come from files and providers through gcego/registry. Browsers send page identities, record keys and action names only, and nothing names a model, module or class.
- **Nothing is implied:** no column, field, operation or permission is guessed; destructive actions exist only when declared.
- **Every page is authorized:** module, resource, operation and policy, plus the record. Every action is authorized again, and every Livewire update re-checks.
- **Tenants stay apart:** data comes through gcego/data scopes, so cross-tenant records are 404, and parents and relationships are scoped as well.
- **No models or hidden attributes in Livewire state.** Page state is identities and keys (locked).

## Testing

```bash
composer test       # Pest: unit, integration, feature and security tests (real Laravel, Livewire, SQLite)
composer analyse    # PHPStan (max level)
composer format     # Pint
```

See [docs/](docs) for the architecture, discovery, resources, pages, relationships, navigation, authorization, extensions and the API reference.
