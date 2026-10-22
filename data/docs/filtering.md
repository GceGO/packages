# Filtering

## Fixed conditions (`Filter`): mandatory, trusted

```php
new Filter('is_published', true);
new Filter('level', 2, FilterOperator::GreaterThanOrEqual);
new Filter('status', ['draft', 'review'], FilterOperator::In);
new Filter('archived_at', operator: FilterOperator::Null);
new Filter('topics.name', 'Algebra');          // Eloquent relation path (whereHas)
```

`Equals null` means `IS NULL`, and `NotEquals null` means `IS NOT NULL`.
Request input cannot remove a `Filter`.

## Request filters (`Filterable`): allowlisted

```php
new Filterable('status', options: ['draft', 'published']);                         // eq and in, strings
new Filterable('level', [FilterOperator::Equals, FilterOperator::Between], ValueType::Integer);
new Filterable('subject', type: ValueType::Integer, column: 'subject_id');         // key != column
new Filterable('topic', column: 'topics.name');                                    // relation path
new Filterable('published', FilterOperator::Equals, ValueType::Boolean, column: 'is_published');
```

The request names the **key**; the **column** comes only from the definition.

```
?filter[status]=published                 eq
?filter[level][]=1&filter[level][]=3      in (a plain list)
?filter[level][in]=1,3                    in (comma-separated)
?filter[level][between]=2,3               between
?filter[level][gte]=2&filter[level][lt]=4 several operators: AND
?filter[code][null]=1                     null / not_null take no value (1, true or empty)
?filter[status]=                          empty value: no filter (a form's "All")
```

| Operator | Request name | Values |
| --- | --- | --- |
| Equals, NotEquals | `eq`, `neq` | one |
| GreaterThan(OrEqual), LessThan(OrEqual) | `gt`, `gte`, `lt`, `lte` | one |
| In, NotIn | `in`, `not_in` | 1 to `filters.max_values` (100) |
| Between | `between` | exactly two (inclusive) |
| Null, NotNull | `null`, `not_null` | none |
| Contains, NotContains, StartsWith, EndsWith | `contains`, `not_contains`, `starts_with`, `ends_with` | one (strings only) |
| Has, DoesntHave | `has`, `doesnt_have` | none (Eloquent relation paths only) |

## Text, enum and relation filters

```php
Filterable::text('name');                              // contains, not_contains, starts_with, ends_with, eq, neq
Filterable::enum('status', InvoiceStatus::class);      // only the enum's values: eq, neq, in, not_in
Filterable::relation('topics');                        // ?filter[topics][has]=1, ?filter[topics][doesnt_have]=1
new Filter('name', 'alg', FilterOperator::StartsWith); // fixed text condition
new Filter('topics', operator: FilterOperator::DoesntHave);
```

Text operators are case-insensitive (ILIKE on PostgreSQL; MySQL/MariaDB
follow the column collation) and match `%` and `_` in the value literally.
FilterOperator::comparisons() lists the value-comparison operators only.

## Custom operators and custom filters

```php
final readonly class JsonContains implements CustomOperator
{
    public function name(): string { return 'json_contains'; }
    public function arity(): int { return 1; }
    public function apply(EloquentBuilder|QueryBuilder $query, string $column, mixed $value): void
    {
        $query->whereJsonContains($column, $value);
    }
    public function matches(mixed $actual, mixed $value): bool
    {
        return is_array($actual) && in_array($value, $actual, true);
    }
}

new Filterable('tags', [FilterOperator::Equals, new JsonContains]);   // ?filter[tags][json_contains]=php

new CustomFilter('overdue', fn (Builder $query, bool $overdue) => $overdue
    ? $query->where('due_at', '<', now())->whereNull('paid_at')
    : $query, ValueType::Boolean);                                     // ?filter[overdue]=1
```

A custom operator receives a trusted column and a validated, cast value and
must bind it. A custom filter's handler receives only the validated value
(one value, or a list with `multiple: true`) inside the request filters' own
AND group, so it can narrow but never widen the mandatory scope.

**SQL semantics:** `neq` and `not_in` do not match NULL. A relation filter
means "has at least one related record matching".

## Values

`ValueType` validates and casts every value before it is bound:

| Type | Accepts |
| --- | --- |
| `String` (default) | strings up to 255 characters, no control characters |
| `Integer` | `-?\d{1,18}` |
| `Decimal` | plain decimals (no exponent) |
| `Boolean` | `true`, `false`, `1`, `0` |
| `Date` | `YYYY-MM-DD` (a valid date) |
| `DateTime` | `YYYY-MM-DD HH:MM[:SS]` or with `T`; normalized to `Y-m-d H:i:s`, no time-zone conversion |

`options` restricts values to a list (after casting).

## Errors

All of these are `InvalidFilterException`, a 400 response:

- **Unknown key:** in strict mode, which is the default.
- **Bad operator:** not allowed for that key.
- **Bad value:** wrong type, not an option, or the wrong number of values.

Messages name keys and operators, never submitted values. In non-strict mode
(`gcego-data.queries.strict = false`), unknown keys are ignored; invalid
values are still rejected.

## Composition

```
(mandatory constraints) AND (fixed filters) AND (request filter 1 AND request filter 2 ...) AND (search)
```

Request filters cannot widen a source. Filtering `published=false` on a
source with `new Filter('is_published', true)` returns nothing.
