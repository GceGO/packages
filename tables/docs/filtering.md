# Filtering

Filters own their control and state. gcego/data owns the query: each
filter declares a `Filterable` (the field comes from the definition, never
the request) or, for custom filters, a trusted constraint.

```php
new SelectFilter('status', 'Status', new Property(SelectFilter::Options, ['draft' => 'Draft', 'published' => 'Published']))
new SelectFilter('kind', 'Kind', new Property(SelectFilter::Options, Kind::class), new Property(SelectFilter::Multiple, true))
new SelectFilter('category', 'Category', new Property(SelectFilter::Relationship, 'category'), new Property(SelectFilter::Field, 'category_id'))
new BooleanFilter('is_published', 'Published')
new BooleanFilter('archived_at', 'Archived', new Property(BooleanFilter::Filled, true))
new DateFilter('starts_on', 'Starts on')
new DateFilter('created_at', 'Created on', new Property(DateFilter::Time, true))
new DateFilter('due_on', 'Due before', new Property(DateFilter::Operator, 'before'))
new DateRangeFilter('starts_on', 'Starts between')
new CustomFilter('overdue', 'Overdue', new FilterQuery(fn (Builder $query, bool $on) => $query->where('due_on', '<', today())))
new CustomFilter('band', 'Band', new Property(CustomFilter::Input, 'select'), new Property(CustomFilter::Options, ['low' => 'Low', 'high' => 'High']),
    new FilterQuery(fn (Builder $query, string $band) => $band === 'high' ? $query->where('score', '>=', 70) : $query->where('score', '<', 70)))
```

## Common properties

| Property | Meaning |
| --- | --- |
| `Field` | the source field (default: the filter name; a relation path filters through `whereHas`) |
| `Default` | the initial state |
| `Placeholder` | the empty option's text |

## Options

| Source | Details |
| --- | --- |
| Static map or backed enum | Fixed at definition time. |
| `Relationship` | A BelongsTo relation of the source model. Options are the related records, read through gcego/data as the current user, so tenant scope, policies and data scopes apply, with at most 100 options. |
| `Callback` | Trusted application code; it runs without those scopes, so it must apply them itself. Prefer `Relationship` for records. |

Submitted values must be one of the options the user can see.

## Dates

| Topic | Details |
| --- | --- |
| Whole days | Date filters compare whole days, so they work whether the driver stores `Y-m-d` or `Y-m-d 00:00:00`. |
| `Time` | Days are taken in the display zone (`gcego-tables.dates.timezone`) and converted to `app.timezone` bounds. |
| Ranges | Inclusive; either end is optional, and a range whose start is after its end is refused. |

## State and UI

| Topic | Details |
| --- | --- |
| Shape | `filters.<name>`. Unknown names are refused on update (HTTP 400). Invalid values are refused, or reset with a notice when they come from the URL. |
| UI | Active filters show as removable indicators, and "Reset filters" clears them all. |
| Resets | Changing filters resets the page and the selection. |
| Unreadable columns | A filter on the field of a column the user may not read is not offered and is refused, because it would reveal values. |
