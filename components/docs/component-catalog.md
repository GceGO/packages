# Component catalog

The required provider registers a scoped catalog with **84 built-in component
factories**. These are declarative UI models. Consuming packages supply rendering,
JavaScript behavior, authorization, persistence and HTTP input validation.
An email control does not validate an HTTP request or send mail; upload and media
controls do not store files, execute scripts or contact external services.

```php
use Gcego\Components\Component;
use Gcego\Components\Facades\Components;

return Components::schema([
    Component::card('account')->components([
        Component::form('profile')->components([
            Component::textInput('name')->required()->maxLength(100),
            Component::emailInput('email')->required(),
            Component::button('save')->label('Save')->buttonType('submit'),
        ]),
        Component::chart('visits')->chartType('line')->data([1, 2, 3]),
    ]),
])->assertValid();
```

All factories require a non-empty name and return immutable `ComponentBuilder`
instances. Both `Component::textInput(name: 'email')` and
`Components::component('textInput', 'email')` work. Direct imports work through
`Forms\TextInput::define('email')`. Extend a component and override `builderClass()`
when you need specialized fluent methods.

Every type inherits `name`, `label`, `description` and `id`, plus the
`Foundation\SchemaComponent` concerns: `hidden: bool`, `visible: bool`,
`hiddenOn: list<string>`, `visibleOn: list<string>`, `columnSpan: int|"full"|breakpoint map`,
`columnStart: int|breakpoint map` and `extraAttributes: array`. Section, group, grid,
fieldset, tab, card, panel, wizard step, form and schema also take
`columns: int|breakpoint map`. Every catalog class has `make()`, and every setter
accepts a closure (see the README's Filament-style authoring section). Additional properties
are below. Exact property names and snake_case setters work. Boolean switches may
omit their argument; properties colliding with builder methods use `set()`.
Unknown properties fail validation. Call `assertValid()` to enforce all schema rules.

Positive dimensions, row/column counts, page numbers and sizes reject zero.
Text lengths, totals and durations may be zero. Text length and numeric bounds
must be ordered. Numeric fields require finite values and a positive step.
Heading levels range from 1 to 6. Map coordinates use geographic ranges; zoom
ranges from 0 to 24. URL and rich text values remain ordinary strings; consuming
renderers must enforce URL policies and escape or sanitize content.

## Layout

Classes live under `Gcego\Components\Layout`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `section()` | `Section` | yes | — |
| `group()` | `Group` | yes | — |
| `item()` | `Item` | no | — |
| `container()` | `Container` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool` |
| `grid()` | `Grid` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `columns: int\|breakpoint map` |
| `stack()` | `Stack` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool` |
| `row()` | `Row` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool` |
| `column()` | `Column` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `span: int` |
| `card()` | `Card` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |
| `panel()` | `Panel` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |
| `tabs()` | `Tabs` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `active: string` |
| `tab()` | `Tab` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |
| `accordion()` | `Accordion` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `active: string` |
| `accordionItem()` | `AccordionItem` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |
| `fieldset()` | `Fieldset` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |
| `modal()` | `Modal` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool`, `open: bool`, `dismissible: bool` |
| `drawer()` | `Drawer` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool`, `open: bool`, `dismissible: bool` |
| `splitter()` | `Splitter` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool` |
| `wizard()` | `Wizard` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `active: string` |
| `wizardStep()` | `WizardStep` | yes | `align: string`, `justify: string`, `gap: string`, `width: string`, `height: string`, `hidden: bool`, `title: string`, `icon: string`, `disabled: bool` |

## Forms

Classes live under `Gcego\Components\Forms`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `form()` | `Form` | yes | `action: string`, `method: string`, `disabled: bool` |
| `textInput()` | `TextInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `textarea()` | `Textarea` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int`, `rows: int` |
| `numberInput()` | `NumberInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: float`, `min: float`, `max: float`, `step: float` |
| `emailInput()` | `EmailInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `passwordInput()` | `PasswordInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `searchInput()` | `SearchInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `urlInput()` | `UrlInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `telInput()` | `TelInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int` |
| `select()` | `Select` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `options: array`, `searchable: bool`, `value: string` |
| `multiSelect()` | `MultiSelect` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `options: array`, `searchable: bool`, `value: array` |
| `checkbox()` | `Checkbox` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `checked: bool` |
| `radio()` | `Radio` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `options: array`, `searchable: bool`, `value: string` |
| `toggle()` | `Toggle` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `checked: bool` |
| `datePicker()` | `DatePicker` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `min: string`, `max: string`, `format: string`, `timezone: string` |
| `dateTimePicker()` | `DateTimePicker` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `min: string`, `max: string`, `format: string`, `timezone: string` |
| `timePicker()` | `TimePicker` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `min: string`, `max: string`, `format: string`, `timezone: string` |
| `fileUpload()` | `FileUpload` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `accept: array`, `multiple: bool`, `maxFiles: int`, `maxSize: int` |
| `hiddenInput()` | `HiddenInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string` |
| `colorPicker()` | `ColorPicker` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string` |
| `rangeInput()` | `RangeInput` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: float`, `min: float`, `max: float`, `step: float` |
| `richEditor()` | `RichEditor` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int`, `rows: int` |
| `codeEditor()` | `CodeEditor` | no | `required: bool`, `disabled: bool`, `readOnly: bool`, `hidden: bool`, `placeholder: string`, `helpText: string`, `autocomplete: string`, `value: string`, `minLength: int`, `maxLength: int`, `rows: int`, `language: string` |

## DataDisplay

Classes live under `Gcego\Components\DataDisplay`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `table()` | `Table` | yes | `data: array`, `pageSize: int`, `sortable: bool`, `searchable: bool`, `selectable: bool` |
| `tableColumn()` | `TableColumn` | no | `field: string`, `format: string`, `align: string`, `sortable: bool`, `searchable: bool` |
| `text()` | `Text` | no | `content: string`, `color: string` |
| `heading()` | `Heading` | no | `content: string`, `color: string`, `level: int` |
| `badge()` | `Badge` | no | `content: string`, `color: string` |
| `avatar()` | `Avatar` | no | `src: string`, `alt: string`, `width: int`, `height: int` |
| `icon()` | `Icon` | no | `icon: string`, `size: string`, `color: string` |
| `stat()` | `Stat` | no | `content: string`, `color: string`, `value: string`, `icon: string` |
| `keyValueList()` | `KeyValueList` | no | `data: array` |
| `descriptionList()` | `DescriptionList` | no | `data: array` |
| `listView()` | `ListView` | yes | — |
| `listItem()` | `ListItem` | yes | — |
| `progress()` | `Progress` | no | `value: float`, `max: float` |
| `timeline()` | `Timeline` | yes | — |
| `timelineItem()` | `TimelineItem` | yes | `title: string`, `date: string`, `icon: string` |
| `codeBlock()` | `CodeBlock` | no | `content: string`, `language: string` |

## Navigation

Classes live under `Gcego\Components\Navigation`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `menu()` | `Menu` | yes | — |
| `menuItem()` | `MenuItem` | yes | `url: string`, `icon: string`, `target: string`, `disabled: bool`, `active: bool` |
| `breadcrumbs()` | `Breadcrumbs` | yes | — |
| `breadcrumb()` | `Breadcrumb` | no | `url: string`, `icon: string`, `target: string`, `disabled: bool`, `active: bool` |
| `pagination()` | `Pagination` | no | `page: int`, `pageSize: int`, `total: int` |
| `link()` | `Link` | no | `url: string`, `icon: string`, `target: string`, `disabled: bool`, `active: bool` |

## Feedback

Classes live under `Gcego\Components\Feedback`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `alert()` | `Alert` | yes | `title: string`, `message: string`, `status: string`, `icon: string`, `dismissible: bool` |
| `toast()` | `Toast` | yes | `title: string`, `message: string`, `status: string`, `icon: string`, `dismissible: bool`, `duration: int` |
| `banner()` | `Banner` | yes | `title: string`, `message: string`, `status: string`, `icon: string`, `dismissible: bool` |
| `emptyState()` | `EmptyState` | yes | `title: string`, `message: string`, `status: string`, `icon: string` |
| `skeleton()` | `Skeleton` | no | `rows: int`, `width: string`, `height: string` |
| `spinner()` | `Spinner` | no | `size: string`, `color: string` |
| `tooltip()` | `Tooltip` | yes | `title: string`, `message: string`, `status: string`, `icon: string` |
| `popover()` | `Popover` | yes | `title: string`, `message: string`, `status: string`, `icon: string` |

## Actions

Classes live under `Gcego\Components\Actions`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `button()` | `Button` | no | `url: string`, `icon: string`, `variant: string`, `color: string`, `disabled: bool`, `loading: bool`, `action: string`, `buttonType: string` |
| `buttonGroup()` | `ButtonGroup` | yes | — |
| `dropdown()` | `Dropdown` | yes | `trigger: string` |
| `dropdownItem()` | `DropdownItem` | yes | `url: string`, `icon: string`, `variant: string`, `color: string`, `disabled: bool`, `loading: bool` |

## Media

Classes live under `Gcego\Components\Media`.

| Factory | Class | Children | Additional properties |
| --- | --- | --- | --- |
| `image()` | `Image` | no | `src: string`, `alt: string`, `width: int`, `height: int` |
| `video()` | `Video` | no | `src: string`, `controls: bool`, `autoplay: bool`, `muted: bool`, `loop: bool`, `poster: string` |
| `audio()` | `Audio` | no | `src: string`, `controls: bool`, `autoplay: bool`, `muted: bool`, `loop: bool` |
| `embed()` | `Embed` | no | `src: string`, `title: string`, `width: int`, `height: int` |
| `map()` | `Map` | no | `latitude: float`, `longitude: float`, `zoom: float` |
| `chart()` | `Chart` | no | `chartType: string`, `data: array`, `options: array` |
| `calendar()` | `Calendar` | no | `events: array`, `view: string`, `timezone: string` |

## Custom registrations

Inspect registrations with `Components::catalog()->all()` or resolve an exact
alias with `->resolve('textInput')`. Registration accepts trusted concrete
component classes and rejects duplicate, case-conflicting or reserved aliases.
Class names are application configuration, not a request-input authorization policy.

Register extensions from your Laravel service provider so every request/job scope
receives them, including in persistent workers:

```php
use Gcego\Components\Catalog\ComponentCatalog;
use App\Components\DashboardSection;

public function register(): void
{
    $this->app->afterResolving(ComponentCatalog::class,
        static function (ComponentCatalog $catalog): void {
            $catalog->register('dashboardSection', DashboardSection::class);
        },
    );
}
```

Use `Component::dashboardSection('dashboard')` or
`Components::component('dashboardSection', 'dashboard')`. One-off registration
through `Components::catalog()->register(...)` lasts for the current scope only.
Custom alias autocomplete requires application PHPDoc.

`Forms\FieldComponent`, `Forms\TextField` and `Forms\NumericField` centralize shared
form contracts. Extend these abstract bases or concrete controls, start from the
parent schema and assign a unique type identifier.

## Fluent property schemas

`Schema\Field` builds property definitions and is distinct from `Forms\FieldComponent`.
Factories: `string`, `boolean`, `integer`, `number`, `array`, `list`, `mixed`, and
`of($name, Type|array)` for unions, enum and object types. Modifiers: `mustExist`,
`nullable`, `defaultsTo`, `allowedValues`, `rule`, `normalizeUsing`, `description`.
`build()` returns a `PropertyDefinition`.

```php
use Gcego\Components\Schema\Field;

return parent::defineSchema()->withFields([
    Field::string('name')->mustExist(),
    Field::boolean('searchable')->defaultsTo(false),
]);
```

`withFields()` accepts field builders and existing property definitions, following
`withProperties()` replacement semantics. Required fields cannot declare defaults.
Null defaults require a nullable definition.

## Complete example

[Full usage](usage.md) composes built-ins into a 49-component dashboard. Run
`php examples/run.php --normalized` to inspect its full canonical output.
`php examples/run.php --custom` exercises a custom component, builder, value object
and configuration node. The examples require the same service provider as applications.
