# Generators

Each generator plans its files, then writes them in one transaction (see [stubs.md](stubs.md) for templates and [security.md](security.md) for the guarantees). The examples below are actual output, generated in the test suite for a `Subject` model (`App\Models\Subject` in an application).

Common options: `--dry-run`, `--force`, `--test` (adds a gcego/testing test), `--pest` / `--phpunit`, `--format=json`.

## Module

`php artisan gcego:make-module Academics`

```php
<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Modules\Module;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Types\DashboardPage;
use Gcego\Security\Requirements\Authenticated;

// Discovered as "module:academics". Each file in Resources/ returns a Resource
// (php artisan gcego:make-resource) and is included below.
return new Module(
    new Property(Module::Name, 'academics'),
    new Property(Module::Title, 'Academics'),
    new Property(Module::Navigation, true),

    new DashboardPage(
        new Property(DashboardPage::Name, 'overview'),
        new Property(DashboardPage::Title, 'Academics'),
        new Authenticated,

        new Section('Academics', new Content('Academics overview.')),
    ),

    ...array_map(static fn (string $resource): object => require $resource, glob(__DIR__.'/Resources/*.php') ?: []),
);
```

A module needs at least one page or resource to be valid, so the generated module starts with an overview page. Every `Resources/*.php` file is included automatically.

## Resource

`php artisan gcego:make-resource Subject --model="App\Models\Subject" --module=academics [--panel=admin] [--read-only] [--delete]`

```php
<?php

declare(strict_types=1);

use Gcego\Actions\Create;
use Gcego\Actions\Edit;
use Gcego\Cli\Tests\Fixtures\Enums\Status;
use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\NumberInput;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;
use Gcego\Infolists\Entries\Boolean as BooleanEntry;
use Gcego\Infolists\Entries\Date as DateEntry;
use Gcego\Infolists\Entries\Number as NumberEntry;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Infolists\Infolist;
use Gcego\Modules\Resource;
use Gcego\Tables\Columns\Boolean;
use Gcego\Tables\Columns\Date;
use Gcego\Tables\Columns\Number;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Table;

// A resource of module "academics" (included by its Module.php). Pages are
// authorized by Subject's policy (viewAny, view, create, update).
return new Resource(
    new Property(Resource::Name, 'subjects'),
    new Source(Subject::class),
    new Property(Resource::RecordKey, 'id'),
    new Property(Resource::RecordTitle, 'name'),

    new Table(
        new Search,
        new Text('name', 'Name', new Property(Text::Searchable, true), new Property(Text::Sortable, true)),
        new Text('code', 'Code'),
        new Boolean('is_published', 'Is Published'),
        new Date('published_on', 'Published On'),
        new Number('credits', 'Credits'),
        new Text('status', 'Status'),
    ),

    new Form(
        new TextInput('name', 'Name', new Property(TextInput::Required, true)),
        new TextInput('code', 'Code', new Property(TextInput::Required, true)),
        new Textarea('description', 'Description'),
        new Toggle('is_published', 'Is Published'),
        new DatePicker('published_on', 'Published On'),
        new NumberInput('credits', 'Credits', new Property(NumberInput::Integer, true)),
        new Select('status', 'Status', new Property(Select::Default, 'draft'), new Options(Status::class)),
    ),

    new Infolist(
        new TextEntry('name', 'Name'),
        new TextEntry('code', 'Code'),
        new TextEntry('description', 'Description', new Property(TextEntry::Multiline, true)),
        new BooleanEntry('is_published', 'Is Published'),
        new DateEntry('published_on', 'Published On'),
        new NumberEntry('credits', 'Credits'),
        new TextEntry('status', 'Status'),
    ),

    new Create,
    new Edit,
);
```

How the model becomes the resource:

- **Fields:** read from `$fillable`, casts and the table's columns. With no database connection they are inferred from `$fillable` and casts, and a warning says so.
- **Form:** only `$fillable` attributes. Required fields come from NOT NULL columns without a default; literal column defaults become field defaults.
- **Left out:** keys, timestamps, foreign keys you did not make fillable, `$hidden` attributes, and anything that looks like a password, token or secret.
- **Field types:**

  | Type | Form | Table | Infolist |
  | --- | --- | --- | --- |
  | string | TextInput | Text | Text |
  | text | Textarea | — | Text (multiline) |
  | boolean | Toggle | Boolean | Boolean |
  | date | DatePicker | Date | Date |
  | datetime | DateTimePicker | DateTime | DateTime |
  | integer, decimal | NumberInput | Number | Number |
  | enum | Select | Text | Text |

- **Table:** the first text column is searchable and sortable.
- **Actions:** Create and Edit (plus Delete with `--delete`), authorized by the model's policy. A warning is printed when the model has no policy, or when tenancy is enabled but the model is not tenant-scoped.
- **Existing modules:** a module not generated by the CLI does not include `Resources/` automatically; the next steps print the `require` line to add.

## Blueprint

`php artisan gcego:make-blueprint Subjects --model="App\Models\Subject"` writes a Blueprint with a Source, a table and a form, guarded by `new Ability('viewAny', Subject::class)`.

## Page and panel

```php
<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Section;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Page;
use Gcego\Security\Requirements\Ability;

// Discovered as "page:reports".
return new Page(
    new Property(Page::Name, 'reports'),
    new Property(Page::Title, 'Reports'),
    new Ability('view-reports'),

    new Section('Reports', new Content('Reports content.')),
);
```

```php
<?php

declare(strict_types=1);

use Gcego\Components\Properties\Property;
use Gcego\Panels\Branding\Branding;
use Gcego\Panels\Definitions\ModuleReference;
use Gcego\Panels\Panel;
use Gcego\Security\Requirements\Ability;

// Discovered as "panel:admin". Who may enter: whoever the "access-admin" Gate ability allows (deny by default).
return new Panel(
    new Property(Panel::Name, 'admin'),
    new Property(Panel::Title, 'Admin'),
    new Property(Panel::Path, 'admin'),
    new Ability('access-admin'),

    new Branding(new Property(Branding::Name, 'Admin')),
    new ModuleReference('academics'),
);
```

`make-panel` validates the path, domain, guard, theme, tenant mode and layout. It refuses a path another panel or an application route already uses, and checks that referenced modules and pages exist. It never defines the Gate ability.

## Forms, tables and infolists

`php artisan gcego:make-form SubjectForm --module=academics --model="App\Models\Subject"` writes a reusable part file, for example `Forms/SubjectForm.php`:

```php
<?php

declare(strict_types=1);

use Gcego\Cli\Tests\Fixtures\Enums\Status;
use Gcego\Components\Properties\Property;
use Gcego\Forms\Configuration\Options;
use Gcego\Forms\Fields\DatePicker;
use Gcego\Forms\Fields\NumberInput;
use Gcego\Forms\Fields\Select;
use Gcego\Forms\Fields\Textarea;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Fields\Toggle;
use Gcego\Forms\Form;

// A reusable form: use it in a Resource or Blueprint with require __DIR__.'/Forms/SubjectForm.php'.
return new Form(
    new TextInput('name', 'Name', new Property(TextInput::Required, true)),
    new TextInput('code', 'Code', new Property(TextInput::Required, true)),
    new Textarea('description', 'Description'),
    new Toggle('is_published', 'Is Published'),
    new DatePicker('published_on', 'Published On'),
    new NumberInput('credits', 'Credits', new Property(NumberInput::Integer, true)),
    new Select('status', 'Status', new Property(Select::Default, 'draft'), new Options(Status::class)),
);
```

## Widget

`php artisan gcego:make-widget StudentStats --type=trend --model="App\Models\Student"`:

```php
<?php

declare(strict_types=1);

use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Components\Properties\Property;
use Gcego\Security\Requirements\Ability;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Statistics\Stat;

// Discovered as "widget:student_stats".
return new Stat(
    'Student Stats',
    new Property(Stat::Name, 'student_stats'),
    new Aggregate(Subject::class, date: 'created_at', compare: true),
    new Trend,
    new DateRange('last_30_days'),
    new Ability('viewAny', Subject::class),
);
```

| Type | Generated |
| --- | --- |
| `stat` | Stat over an Aggregate count |
| `trend` | Stat with a period comparison and Trend |
| `chart` | LineChart of daily counts |
| `progress` | Progress |
| `recent` | RecentRecords of a blueprint (`--blueprint`); the blueprint's authorization applies |
| `custom` | Stat with a generated `WidgetDataProvider` class (`--ability` required) |

## Action

`php artisan gcego:make-action PublishSubject --module=academics` writes the handler class `App\GCEGo\Support\Actions\PublishSubject` (an `ActionHandler`) and this definition:

```php
<?php

declare(strict_types=1);

use App\GCEGo\Support\Actions\PublishSubject;
use Gcego\Actions\Action;
use Gcego\Actions\Configuration\Handler;
use Gcego\Components\Properties\Property;

// Add to a Resource or Blueprint with require __DIR__.'/Actions/PublishSubject.php'.
return new Action(
    'publish-subject',
    'Publish Subject',
    new Property(Action::Ability, 'publishSubject'),
    new Property(Action::Record, true),
    new Property(Action::Confirmation, true),
    new Handler(PublishSubject::class),
);
```

The action is denied until the model's policy has `publishSubject()`.

## Workflow

```php
<?php

declare(strict_types=1);

use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Workflows\States\State;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;

// Discovered as "workflow:content-approval". Each transition needs its policy ability
// on the subject ("update" to submit, "approve" to decide): nobody else may run it.
return new Workflow(
    'content-approval',
    'Content Approval',
    new State('draft', 'Draft'),
    new State('submitted', 'Submitted'),
    new State('approved', 'Approved', terminal: true, color: 'success'),
    new State('rejected', 'Rejected', terminal: true, color: 'danger'),

    new Transition('submit', 'draft', 'submitted', ability: 'update'),
    new Transition('approve', 'submitted', 'approved', ability: 'approve', comment: 'optional'),
    new Transition('reject', 'submitted', 'rejected', ability: 'approve', comment: 'required'),

    subject: Subject::class,
);
```

## Settings

`php artisan gcego:make-settings AcademicSettings --scope=tenant --page`:

```php
<?php

declare(strict_types=1);

use Gcego\Security\Requirements\Ability;
use Gcego\Settings\Setting;
use Gcego\Settings\SettingsGroup;

// Discovered as "settings:academic". Keys are global: read them with app(SettingsManager::class)->get('academic_enabled').
return new SettingsGroup(
    'academic',
    'Academic',
    new Ability('academic.manage'),
    new Setting(name: 'academic_enabled', label: 'Academic enabled', default: true),
    new Setting(name: 'academic_label', label: 'Academic label', default: 'Academic', rules: ['max:120']),
    scopes: ['tenant'],
);
```

## Analytics

- `gcego:make-dataset StudentPerformance --model=...`: fields from the model, enum columns as dimensions, `created_at` as the date column.
- `gcego:make-metric ActiveStudents --dataset=student-performance [--aggregate=count] [--field=]`, or `--model=...` (then it carries the model's `viewAny` requirement).
- `gcego:make-report AcademicPerformance --dataset=student-performance [--export]`:

```php
<?php

declare(strict_types=1);

use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Security\Requirements\Ability;

// Discovered as "report:academic-performance".
return new Report(
    'academic-performance',
    'Academic Performance',
    new Ability('academic-performance.view'),
    new ReportColumn('status', 'Status'),
    new ReportColumn('records', 'Records', aggregate: 'count'),
    dataset: 'student-performance',
);
```

## Navigation, layout and notification

- `gcego:make-navigation AcademicNavigation --item="Subjects=gcego.admin.academics.subjects.index" --permission=academics.view`: route names are checked, and paths start with `/`.
- `gcego:make-layout AcademicDashboard --columns=3`: a Section with a responsive Grid. It requires authentication unless you pass `--ability`.
- `gcego:make-notification WelcomeStudent --channels=database,mail`: a class whose `make()` builds a gcego/notifications `Notification` and whose `sendTo($user)` sends it.

## Test

`php artisan gcego:make-test SubjectResourceTest --type=resource --target=academics.subjects`:

```php
<?php

declare(strict_types=1);

use Gcego\Testing\GcegoTest;

it('declares resource academics.subjects in a healthy module', function (): void {
    GcegoTest::module('academics')->assertHasResource('subjects')->assertHealthy();
});
```

The test types and their targets:

| `--type` | `--target` |
| --- | --- |
| component | an identity (`widget:students`) or a definition file |
| blueprint | a blueprint name |
| form, table | a part file |
| action | `definition/action` (checks guests are denied) |
| resource | `module.resource` |
| page | a page name (registered, healthy, requires sign-in) |
| module | a module name |
| panel | a panel name |
| workflow | a workflow name |
| settings | a settings group |
| analytics | an analytics identity |
| security | an ability guests must not have |
| tenant | a tenant-scoped model class |

Generated tests check what holds for any application. They don't assume users, records or factories; add your own cases next to them.
