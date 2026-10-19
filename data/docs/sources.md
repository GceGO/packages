# Sources

## Forms

```php
new Source(Subject::class);                                    // Eloquent (shorthand)
Source::model(Subject::class, new Paginate(10));
Source::table('report_rows', new Sortable('total'));           // query builder over a table
Source::builder(fn () => DB::table('enrolments')->join(...)->select(...), new Property(Source::Key, 'enrolments.id'));
Source::collection(fn () => config('school.terms'));           // in-memory items
```

The provider is inferred: a model means `eloquent`, items mean `collection`,
and a table or builder means `query`. Set `Source::Provider` to use a custom
provider (see [providers](providers.md)).

## Properties

Every setting has a Property form, using the gcego/components property
system. Property and node forms are equivalent. Declaring one setting twice
(both forms, or two `Paginate` nodes) is an error, never a silent override.

| Constant | Value | Equivalent node |
| --- | --- | --- |
| `Source::Model` | model class (the positional shorthand) | |
| `Source::Provider` | provider name | |
| `Source::Table`, `Source::Connection` | table, connection name | |
| `Source::Key` | unique key for query/collection sources (default `id`) | |
| `Source::Columns` | selected columns (query sources) | |
| `Source::Builder`, `Source::Items` | `new Callback(fn () => ...)` | |
| `Source::Query` | `new Callback(fn ($query) => ...)` | `new Query(...)` |
| `Source::Filters` | `['is_published' => true, 'level' => [1, 2]]` | `Filter` (lists become `in`) |
| `Source::Search` | `['name', 'code']` | `new Search(...)` |
| `Source::Sort` | `['-level', 'name']` | `Sort` nodes |
| `Source::Pagination` | `25` | `new Paginate(25)` |
| `Source::Relationships` | `['topics']` | `new With(...)` |
| `Component::Name` | name used in errors, diagnostics and the query-builder ability | |

```php
new Source(
    new Property(Source::Model, Subject::class),
    new Property(Source::Pagination, 25),
    new Property(Source::Sort, ['-level', 'name']),
);
```

## Validation

- **Construction** only checks structure, like every component.
- **On first use** the source is validated in full, and `InvalidSourceException`
  names every problem:
  - a missing class, or one that isn't a `Model`;
  - none or several origins;
  - invalid properties;
  - duplicate settings;
  - unknown relations.
- **Inspection:** `$blueprint->validate()` and `DataManager::inspect($source)`
  report the same problems without throwing.

## Names

The default name is the snake-case plural of the model (`subjects`), or the
table name, `collection` or `query`. Override it with
`new Property(Component::Name, 'catalogue')`.

## In blueprints

```php
new Blueprint('subjects', new Source(Subject::class), /* table, form, ... */);

$source = app(DataManager::class)->sourceOf($blueprint);   // $blueprint->part('source')
```

A blueprint has at most one source.

- **Registry:** gcego/registry stores only scalar metadata, so callbacks never
  reach the manifest. This is tested with `gcego:registry-cache`.
- **Runtime:** when gcego/runtime is installed, the Source part renders as
  nothing.
