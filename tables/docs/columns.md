# Columns

Every column takes `(name, label)` shorthand or `Property` syntax. The
name is a trusted path into the row:
- `"name"` for an attribute;
- `"category.name"` through a relation (eager-loaded automatically, never lazily);
- `"tags.label"` across a to-many relation, which shows a list.

## Common properties (`Column::…`)

| Property | Values |
| --- | --- |
| `Searchable` | `true` or a list of source fields (`['first_name', 'last_name']`) |
| `Sortable`, `SortColumn` | sort by this column; relation paths need a `SortColumn` of the source |
| `Hideable` | `false`: always shown, users cannot hide it |
| `Visible` | `false`: hidden until the user shows it |
| `Align` | `start`, `center` or `end` |
| `Width` | `xs`, `sm`, `md`, `lg`, `xl` or `auto` |
| `Wrap` | allow line wrapping |
| `Limit` | truncate to N characters (full text in the tooltip) |
| `Tooltip` | a fixed text, or `true` for the full value |
| `Placeholder` | shown for empty values (default "—") |
| `VisibleFrom` | `sm`, `md`, `lg` or `xl`: hide the column on smaller screens |
| `Exportable` | `false` to leave the column out of exports |

Configuration nodes:
- `new Format(...)` for custom text;
- `new Access(read: ..., mask: ...)` for column authorization;
- `new Tailwind(...)`.

## Types

| Column | Specific properties | Notes |
| --- | --- | --- |
| `Text` | `Copyable`, `Prefix`, `Suffix`, `DescriptionColumn` | enums use `label()` when defined |
| `Number` | `Decimals`, `Grouping`, `Locale`, `Prefix`, `Suffix` | intl, locale-aware; invalid values show the placeholder |
| `Money` | `Currency` (ISO 4217, default XAF), `MinorUnits`, `Decimals`, `Locale` | `MinorUnits` converts cents with bcmath; display only |
| `Boolean` | `TrueLabel`, `FalseLabel`, `TrueIcon`, `FalseIcon`, `ShowLabel` | icon shape plus text, never color alone; null is not "No" |
| `Date` | `Format` (PHP format), `Relative` | calendar dates, never shifted |
| `DateTime` | `Format`, `Relative`, `Timezone` | stored in app.timezone, shown in the display zone; formatting uses an immutable copy |
| `Badge` | `Variants` (value → default/success/warning/danger/info/neutral), `Labels`, `Icons`, `DefaultVariant` | always text |
| `Icon` | `Icons` (value → icon), `Tones`, `Labels` | only registered icons render |
| `Image` | `Disk`, `Alt`, `AltColumn`, `Fallback`, `Round` | http(s) URLs or safe storage paths; `loading="lazy"` |
| `Link` | `Route`, `RouteParameters`, `UrlColumn`, `NewTab` | value URLs must be http, https or mailto; new tabs get `rel="noopener noreferrer"` |
| `Progress` | `Min`, `Max` | clamped; `role="progressbar"` with values and text |

```php
new Money('fee', 'Fee', new Property(Money::Currency, 'XAF'));
new Badge('status', 'Status', new Property(Badge::Variants, ['published' => 'success', 'draft' => 'warning']));
new Link('name', 'Subject', new Property(Link::Route, 'subjects.show'), new Property(Link::RouteParameters, ['subject' => 'id']));
new Text('score', 'Grade', new Format(fn (?int $score) => $score === null ? null : ($score >= 50 ? 'Pass' : 'Fail')));
new Text('grade', 'Grade', new Format(GradeFormatter::class));   // implements Contracts\ColumnFormatter
```

`Format` output is plain text and is always escaped.

## Closures (Filament style)

```php
Table::make()->columns([
    Text::make('name')->sortable()->tooltip(fn (Subject $record): string => $record->code),
    Text::make('full_name')->state(fn (Teacher $record): string => $record->first_name.' '.$record->last_name)
        ->searchable(['first_name', 'last_name']),
    Text::make('credits')->formatStateUsing(fn (?int $state): string => $state === null ? 'n/a' : $state.' cr'),
    Badge::make('status')->variants(fn (Subject $record): array => [...]),
    Text::make('salary')->visible(fn (?User $user): bool => $user?->can('viewSalaries') ?? false),
]);
```

| Kind | Evaluated | Injected |
| --- | --- | --- |
| Presentation properties (`tooltip`, `placeholder`, `limit`, `variants`, ...) | per row, on a resolved copy of the column | `$state`, `$record` (also by model type), `$column` |
| `state()` | per row, instead of reading the column name | `$record`, `$column` |
| `formatStateUsing()` | per row, after `state()` | `$state`, `$record`, `$column` |
| `visible(Closure)`, `hidden(Closure)` | once per request | `$user`, `$table`, container services |

Closure results are checked against the property definition (a `limit` closure
must return an int) and are always escaped. Properties that shape the query
(`searchable`, `sortable`, `sort_column`, `hideable`, `visible`, `visible_from`,
`exportable`) cannot be closures, so one request never sorts or searches a
column differently from row to row.

## Extending another module's table

```php
Components::extend('subjects.table', fn (SchemaPatch $patch) => $patch
    ->insertAfter('name', Text::make('short_code')->sortable())
    ->remove('legacy_code'));
```

The patched table is compiled and validated like any other, and the column
access rules, sorting, searching and exports all see the added columns.

## Values

| Rule | Details |
| --- | --- |
| No method calls by name | `Support\ValueResolver` reads attributes (`getAttribute`, so casts and accessors apply), loaded relations, arrays and plain objects. It never calls a method named by data or the browser. |
| No lazy loading | Unloaded relations read as empty instead of querying. |
| `$hidden` is respected | Attributes in the model's `$hidden` are never read. |

## Icons

The trusted set lives in `Support\Icons` (`check`, `x-mark`, `check-circle`, `x-circle`, `star`, `clock`, …). Register more at boot:

```php
app(Icons::class)->register('school', 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62…');
```

Names and path data are validated, and unknown names render nothing.

## Custom column types

Extend `Column` and implement:
- `cellView()`, which returns a Blade view;
- optionally `format()`, `display()` and `exportValue()`.

The renderer registered for `Column` covers subclasses. The cell view
receives:
- `$cell` (from `display()`) and `$column`;
- `$icons` and `$presets`;
- the table's `$styles`.
