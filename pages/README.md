# gcego/pages

Declarative administration pages for GCEGO Admin Studio: index, create, edit, view, dashboard and custom pages with Laravel routes, a page lifecycle, request-scoped context, page and record authorization, headers, breadcrumbs, header actions, layout composition, one reusable Livewire host and diagnostics.

A page is a definition, not a controller and not a Livewire class. You declare what is on it; the package registers its route, authorizes every request, binds its record in scope, renders its header and composes the existing GCEGO engines (tables, forms, infolists, actions, widgets, layouts, navigation) around it.

```php
return new IndexPage(
    new Property(IndexPage::Name, 'subjects'),
    new Property(IndexPage::Title, 'Subjects'),
    new Permission('subjects.view'),
    new Source(Subject::class),
    new Table(new Text('name', 'Subject Name'), new Text('code', 'Code')),
);
```

That file in `app/GCEGo/Pages/` gives you `GET /admin/subjects` (route `gcego.subjects`), authorized by the `subjects.view` permission, with a searchable, sortable, paginated table scoped to the current tenant.

## Contents

- [Installation](#installation)
- [Basic pages](#basic-pages)
- [Page definitions](#page-definitions)
- [Page types](#page-types)
- [Routing](#routing)
- [Route parameters and records](#route-parameters-and-records)
- [Page lifecycle and context](#page-lifecycle-and-context)
- [Headers and breadcrumbs](#headers-and-breadcrumbs)
- [Actions](#actions)
- [Authorization](#authorization)
- [Layout composition](#layout-composition)
- [Livewire hosting](#livewire-hosting)
- [Navigation](#navigation)
- [Styling](#styling)
- [Error handling](#error-handling)
- [Security](#security)
- [Diagnostics](#diagnostics)
- [Testing](#testing)

## Installation

```bash
composer require gcego/pages
```

The service provider is auto-discovered. It needs the other GCEGO packages (components, core, blueprints, registry, runtime, styling, data, security, actions, forms, tables, infolists, widgets, layouts, navigation), which Composer installs.

Publish the configuration or the views when you want to change them:

```bash
php artisan vendor:publish --tag=gcego-pages-config
php artisan vendor:publish --tag=gcego-pages-views
```

Pages render inside `gcego-pages::layouts.app`, a minimal accessible document. Point `rendering.vite` at your stylesheet (the one that imports gcego/chroma's `gcego.css`) and, optionally, `rendering.navigation` at a navigation to show as a sidebar. The future gcego/panels shell replaces this layout.

## Basic pages

Put one definition per file in `app/GCEGo/Pages/` (gcego/registry discovers them; nested folders are fine). Each file returns a page.

### Level 1: a minimal page

<!-- example:minimal -->
```php
use Gcego\Components\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Security\Requirements\Authenticated;

return new Page(
    new Property(Page::Name, 'about'),
    new Property(Page::Title, 'About GCEGO'),
    new Authenticated,

    new Section(
        'About This Platform',

        new Content('GCEGO helps candidates prepare for the GCE.'),
    ),
);
```

`new Authenticated` is required: pages are deny-by-default (see [Authorization](#authorization)). `Content` is escaped text; a Section with nothing in it would be hidden.

### Level 2: a table page

<!-- example:index -->
```php
use App\Models\Subject;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Types\IndexPage;
use Gcego\Security\Permissions\Permission;
use Gcego\Tables\Columns\Badge;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

return new IndexPage(
    new Property(IndexPage::Name, 'subjects'),
    new Property(IndexPage::Title, 'Subjects'),
    new Permission('subjects.view'),
    new Source(Subject::class),
    new PageLink('New subject', 'subjects.create', new Property(PageLink::Intent, 'primary')),

    new Table(
        new Text('name', 'Subject Name'),
        new Text('code', 'Code'),
        new Badge('status', 'Status'),
    ),
);
```

### Level 3: a create page

<!-- example:create -->
```php
use App\Models\Subject;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Pages\Types\CreatePage;
use Gcego\Security\Permissions\Permission;

return new CreatePage(
    new Property(CreatePage::Name, 'subjects.create'),
    new Property(CreatePage::Title, 'Create Subject'),
    new Permission('subjects.create'),
    new Source(Subject::class),

    new Form(
        new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
        new TextInput('code', 'Subject Code', new Property(TextInput::Required, true)),
        new Select('status', 'Status', new Options(['active' => 'Active', 'archived' => 'Archived'])),
    ),
);
```

The page adds the gcego/actions `Create` action its form submits through (validated, writable fields only, `create` policy ability, in a transaction). After saving it opens the new record's view page (`RedirectTo`).

### Level 4: an edit page

<!-- example:edit -->
```php
use App\Models\Subject;
use Gcego\Actions\Delete;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Pages\Types\EditPage;
use Gcego\Security\Permissions\Permission;

return new EditPage(
    new Property(EditPage::Name, 'subjects.edit'),
    new Property(EditPage::Title, 'Edit :record'),
    new Permission('subjects.update'),
    new Source(Subject::class),
    new Delete,

    new Form(
        new TextInput('name', 'Subject Name', new Property(TextInput::Required, true)),
        new TextInput('code', 'Subject Code', new Property(TextInput::Required, true)),
    ),
);
```

`GET /admin/subjects/{record}/edit`. The record is looked up through the Source (tenant and security scopes apply) and the `update` ability is authorized on it before anything renders, again on every Livewire update, and again by the Edit action on save. `:record` in titles is the record's label.

### Level 5: a record detail page

<!-- example:view -->
```php
use App\Models\Subject;
use Gcego\Actions\Delete;
use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\Badge;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Types\ViewPage;
use Gcego\Security\Permissions\Permission;

return new ViewPage(
    new Property(ViewPage::Name, 'subjects.view'),
    new Property(ViewPage::Title, ':record'),
    new Permission('subjects.view'),
    new Source(Subject::class),
    new PageLink('Edit', 'subjects.edit'),
    new Delete,

    new Infolist(
        new Text('name', 'Subject Name'),
        new Text('code', 'Subject Code'),
        new Badge('status', 'Status'),
    ),
);
```

`GET /admin/subjects/{record}`. The Edit link shows only to users who may edit this record; deleting the record returns to the list.

### Level 6: a dashboard

<!-- example:dashboard -->
```php
use App\GCEGo\Widgets\SubjectCount;
use Gcego\Components\Property;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Widgets\Statistics\Stat;

return new DashboardPage(
    new Property(DashboardPage::Name, 'dashboard'),
    new Property(DashboardPage::Title, 'Dashboard'),
    new Property(DashboardPage::Path, ''),
    new Authenticated,

    new Grid(
        new Columns(3),

        new Stat('Subjects', SubjectCount::class, new Authenticated),
        new Stat('Reports', 12, new Permission('reports.view')),
    ),
);
```

`Path ''` puts the dashboard at the prefix (`/admin`). Each widget keeps its own authorization (the Reports stat is shown only with `reports.view`) and data provider.

## Page definitions

`Gcego\Pages\Page` is a gcego/blueprints Blueprint registered under the `page` kind. Its children are the parts it coordinates (a `Source`, tables, forms, infolists, actions, layout components), widgets and page components (`Content`, `PageLink`). Construction is pure: no query, no route, no rendering, no Livewire.

Shorthand: `new Page('about', 'About GCEGO', …)` sets Name and Title.

| Property | Meaning |
| --- | --- |
| `Name` | Required. Lowercase segments separated by `.` (`subjects.create`). Identity, route name and default path. |
| `Title`, `Description` | Plain text, translated; `:record` is replaced by the record label. |
| `Icon` | Icon name shown next to the title. |
| `Path` | URL path below the prefix (`subjects/{record}/edit`). Derived from the name when omitted. |
| `Route` | Route name below the name prefix (default: the name). |
| `Middleware` | Extra route middleware (`['gcego.tenant:required']`). |
| `Where` | Constraints of extra path parameters (`['institution' => '[0-9]+']`). |
| `Record` | Custom pages: the page is about one record (`{record}` in the path). |
| `RecordKey` | Shape of `{record}`: `id`, `uuid`, `ulid`, `slug` or `key`. |
| `RecordTitle` | Record attribute used as its label (default `name`, `title` or `label`). |
| `RecordAbility` | Ability authorized on the record (view pages `view`, edit pages `update`). |
| `TitleProvider` | A `PageTitleProvider` class for trusted dynamic titles and descriptions. |
| `Parent` | Parent page in breadcrumbs (default: the name without its last segment). |
| `Breadcrumbs` | `false` hides the trail. |
| `Width` | `narrow`, `standard`, `wide` or `full`. |
| `Empty` | Text shown when the user may see nothing on the page. |
| `RedirectTo` | Create/edit pages: `view`, `edit`, `index`, `self`, `none` or a page name. |
| `Navigation`, `NavigationLabel`, `NavigationGroup`, `NavigationOrder`, `NavigationIcon` | Opt-in navigation metadata. |

Registry identities replace dots with `-`: `subjects.create` is `page:subjects-create`. Two names that map to the same identity (`subjects.create` and `subjects-create`) are a duplicate registration.

## Page types

| Class | Coordinates | Record | Default path |
| --- | --- | --- | --- |
| `Page`, `Types\CustomPage` | anything | with `Record` | `reports/overview` |
| `Types\IndexPage` | one `Table` and its `Source` | no | `subjects` |
| `Types\CreatePage` | a `Form`, the `Create` action | no | `subjects/create` |
| `Types\EditPage` | a `Form`, the `Edit` action | yes (`update`) | `subjects/{record}/edit` |
| `Types\ViewPage` | an `Infolist` | yes (`view`) | `subjects/{record}` |
| `Types\DashboardPage` | widgets and layouts | no | `dashboard` |

Search, filters, sorting, pagination, row and bulk actions come from gcego/tables; validation and field rules from gcego/forms; field read rules and formatting from gcego/infolists; widget data from gcego/widgets. Pages do not reimplement any of them.

## Routing

Every registered page gets one named GET route when the application boots, after the application's own routes:

| Page | URL | Route name |
| --- | --- | --- |
| `subjects` | `/admin/subjects` | `gcego.subjects` |
| `subjects.edit` | `/admin/subjects/{record}/edit` | `gcego.subjects.edit` |

Routes are built from the registry index (descriptor metadata), so no page definition is loaded to register them. They point to `Gcego\Pages\Http\PageController` and carry only the page identity, so `php artisan route:cache` works; with cached routes nothing is registered at boot at all. A route name or URL that is already taken, by an application route or another page, throws `PageRouteConflictException` at boot.

```php
app(PageManager::class)->url('subjects.edit', 7);           // "/admin/subjects/7/edit"
route('gcego.subjects.edit', ['record' => 7]);              // the same, with Laravel
```

Change `routing.prefix`, `routing.name_prefix`, `routing.middleware` or `routing.domain` in the configuration. To register page routes yourself (gcego/panels does this per panel), turn `routing.enabled` off and call the registrar:

```php
app(PageRouteRegistrar::class)->register(app('router'), prefix: 'school', namePrefix: 'school.', middleware: ['web', 'auth', 'gcego.tenant:required'], filter: fn (PageRouteDefinition $route, DefinitionDescriptor $page) => $page->scope() === 'school');
```

## Route parameters and records

`{record}` is a record page's key. It is constrained by the page's `RecordKey` at the route (`/admin/catalogue/not-a-uuid` is 404 before any query). The key is the model's key, the same key every GCEGO host uses: `id` for integer keys, `uuid` or `ulid` for models using `HasUuids` or `HasUlids`, `slug` for models whose primary key is a slug.

The record is then looked up through the page's gcego/data `Source`, so global scopes, the tenant scope and every security scope apply (out of scope: 404), and the record ability is authorized through the model's policy (403, or 404 when the denial strategy conceals). A valid identifier never grants access.

Other path parameters (`institutions/{institution}/members`) must match `Where`, are passed to the page context, and are kept for the page's links and breadcrumbs.

## Page lifecycle and context

Each request (and each Livewire update) opens the page in stages:

1. **Resolved**: the definition, from gcego/registry by identity only.
2. **ContextCreated**: route parameters checked; principal, tenant, locale, timezone.
3. **Authorized**: the page's requirements (or the configured default).
4. **RecordResolved**: record pages, a scoped lookup and the record ability.
5. **Prepared**: title, description, breadcrumbs and header actions.
6. **Interaction** (Livewire updates), then **Rendering**, **Rendered** and **Completed**.

```php
$context = app(PageManager::class)->open('subjects.view', record: 7);

$context->identity;         // "page:subjects-view"
$context->route;            // "gcego.subjects.view"
$context->tenant;           // "1"
$context->record;           // the authorized Subject (RecordPageContext; server-side only)
$context->header->title;    // PageTitle "Chemistry"
```

Hooks observe the stages without being able to grant anything:

```php
final class AuditPageViews implements PageLifecycleHook
{
    public function handle(PageLifecycleEvent $event): void
    {
        if ($event->stage === PageStage::RecordResolved) {
            logger()->info('page.record', ['page' => $event->identity, 'record' => $event->context?->recordKey()]);
        }
    }
}
```

Register them in `lifecycle.hooks` or with `app(PageManager::class)->hook(AuditPageViews::class)`. A hook is told about a stage only after it passed, so it never sees an unauthorized page or record.

## Headers and breadcrumbs

The header has the breadcrumb trail, one `h1` title, the icon, the description and the header actions. Titles and descriptions are plain text (always escaped): the `TitleProvider`'s, else `Title` (translated, `:record` replaced), else the record label or the name's last segment.

The trail uses gcego/navigation's `Breadcrumb` objects:

- ancestors: `Parent`, else the name without its last segment (`subjects.edit` descends from `subjects`), up to five levels; `breadcrumbs.home` starts every trail;
- record pages add the record, linking to its view page;
- a crumb links only when the user may open that page (and record);
- with `breadcrumbs.navigation` set to a navigation name, the trail starts from that navigation's active trail instead.

```
Subjects › Chemistry › Edit Chemistry
```

## Actions

Header actions are a page's direct children:

- `PageLink`s to other pages, shown only when the user may open the target (for record pages: this record);
- gcego/actions actions placed in `header` (and, on record pages without an infolist, `record`), except the action the page's form submits.

Actions run through gcego/actions only (its Livewire host re-resolves and re-authorizes every run, with confirmation, transactions and feedback). Pages never execute actions; they follow their results:

| Page | Completed action | Goes to |
| --- | --- | --- |
| Create | its form's `create` | `RedirectTo`: the new record's view page, else edit page, else the list |
| Edit | its form's `edit` | stays (`RedirectTo` `self`) |
| Record page | `delete` / `force-delete` of its record | the list |
| Index | a row `view` action | the record's view page |

The result arrives as a browser event, so it is treated as untrusted. It only chooses among targets the definition allows, URLs are built on the server, and the target page authorizes its record again.

## Authorization

Pages use gcego/security. A page's Requirement nodes (`Permission`, `Ability`, `Authenticated`, `TenantMember`, `VerifiedEmail`, `PublicAccess`, …) must all allow. A page without any follows `authorization.default`:

| Value | A page without requirements |
| --- | --- |
| `deny` (default) | is denied to everyone (403) |
| `authenticated` | is open to every signed-in user |
| `public` | is open to everyone, guests included |

Guests are sent to the login page (an `AuthenticationException`; keep `auth` in the route middleware). Children with requirements of their own (a section, a widget, a table) are left out when they deny, without failing the page. Record pages also authorize the record. Everything is checked again on every Livewire update, so a revoked permission, a tenant switch or a deleted record takes effect immediately. A route existing never grants anything.

## Layout composition

Any gcego/layouts component can be a page child: `Section`, `Grid`, `Card`, `Split`, `Tabs`, `Stack`, … Tables, forms, infolists and actions inside layouts are still the page's parts. `?tab=fees` opens the tab named `fees` of the page's tab sets (unknown tabs are ignored).

## Livewire hosting

Pages are not Livewire classes. Every page route renders the one `PageHost` component (`<livewire:gcego-page page="page:subjects-view" record="7" />`). Its browser state is the page identity, the record key, the route parameters and the tab, all `#[Locked]`. It never holds a model, a class name or a decision. The page's tables, forms, infolists, actions and interactive widgets are their own Livewire components, keyed by the page identity and their path, so a page update never re-renders them.

When the record disappears while the page is open (deleted, moved out of scope), the host shows a "no longer available" state with a link back to the list instead of an error.

## Navigation

Pages are never added to a navigation on their own. Opt in per page:

```php
new Property(IndexPage::Navigation, true),
new Property(IndexPage::NavigationGroup, 'Academic'),
```

Then add `Gcego\Pages\Navigation\PageNavigationContributor` to `gcego-navigation.contributors` (or set `navigation.auto_register`). It adds an item per opted-in page to `navigation.target`, carrying the page's own requirements (visible exactly when the page may be opened), active on the page and the pages below it (`gcego.subjects.*`). Record pages and pages with parameters other than the tenant are skipped.

## Styling

Page slots, through gcego/chroma (`new Tailwind(new Slot(Tailwind::Title, ['text-4xl']))`):

`root`, `header`, `title`, `description`, `icon`, `breadcrumbs`, `actions`, `body`, `empty`, `loading`.

Defaults use the theme tokens, so pages follow the theme and dark mode. Content widths come from `Width` (`narrow` `max-w-3xl`, `standard` `max-w-7xl`, `wide`, `full`). Headers stack on small screens and actions wrap; tables, forms and grids keep their own responsive behavior. Override the views with `vendor:publish --tag=gcego-pages-views`.

## Error handling

| Situation | Response |
| --- | --- |
| Unknown page, malformed `{record}`, record missing or out of scope | 404 |
| Page or record denied | 403 (404 when the denial strategy conceals) |
| Guest | redirect to the login page |
| Expired session, invalid form | Laravel's 419 and gcego/forms' validation messages |
| Unexpected error | Laravel's 500 page; gcego/runtime renders failing parts as a placeholder in non-strict mode |

Pages never turn a denial into a successful response and never show exception details.

## Security

- Pages are found by registry identity only; class names, paths and blueprint identities are 404.
- Route parameters are validated, and a valid key never grants access.
- Records are always looked up in scope (tenant, security scopes) and authorized.
- Livewire state holds identities and keys only, all locked.
- Titles, descriptions, labels and content are escaped.
- Decisions are memoized per request and principal, never across users or tenants.
- Page links and redirects lead only to pages the user may open.

## Diagnostics

```php
$report = app(PageManager::class)->inspect('subjects.edit');

$report->isHealthy();
$report->route;          // path, name, registered routes, record key, middleware
$report->components;     // paths, types, requirements
$report->diagnostics;    // route conflicts, missing renderers, missing requirements, records without policies,
                         // links to missing pages, unknown styling slots, size and Livewire-host warnings

app(PageManager::class)->inspectAll();
```

Inspection never opens a page, reads a record or runs a query.

## Testing

```bash
composer test       # Pest: unit, integration and feature tests (real Laravel, Livewire and SQLite)
composer analyse    # PHPStan (max level)
composer format     # Pint
```

See [docs/](docs) for the architecture, definitions, routing, lifecycle, record pages, authorization, actions, rendering, Livewire and the API reference.
