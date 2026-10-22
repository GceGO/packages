# Filtering

## Mandatory constraints: `Constraints`

```php
Constraints::make([
    Filter::make('is_active', true),
    Filter::make('age', 18, GreaterThanOrEqual::make()),
    Filter::make('status', ['draft', 'review'], In::make()),
    Filter::make('archived_at', operator: IsNull::make()),
    Filter::make('department.name', 'Finance'),             // Eloquent relation path (whereHas)
    Filter::make('reports', operator: DoesntHave::make()),  // related records
    Query::make(fn (Builder $query) => $query->whereNull('terminated_at')),
]);
```

Constraints are trusted and always applied. `Equal` with `null` means
`IS NULL`. The value type of a `Filter` is inferred from its value (pass a
fourth argument, such as `Json::make()`, to choose it). Requests can never
override or remove a constraint, and tenant isolation does not depend on
them (see [tenancy](tenancy.md)).

## Request filters: `Filterables`

```php
Filterables::make([
    Filterable::make('age', [Integer::make(), GreaterThanOrEqual::make(), LessThan::make()]),
    Filterable::make('status', [Enum::make(EmployeeStatus::class), Equal::make(), In::make()]),
    Filterable::make('name', [StringType::make(), Contains::make(), StartsWith::make()]),
    Filterable::make('department', [StringType::make(), Column::make('department.name')]),
    Filterable::make('team', [Integer::make(), In::make(), Column::make('team_id'), Options::make([1, 2, 3]), Label::make('Team')]),
    Filterable::make('reports', [Has::make(), DoesntHave::make()]),
    CustomFilter::make('overdue', fn (Builder $query, bool $overdue) => ..., [Boolean::make()]),
]);
```

Each configuration list holds:

- **exactly one value type**; relation filters (`Has`, `DoesntHave`) take none;
- **the permitted operators**; when none are listed, only `Equal` is allowed
  (never every operator);
- optionally `Options` (allowed values), `Label` and `Column` (the trusted
  column when it differs from the key; dotted paths are Eloquent relations).

Duplicate or unknown items, a second value type, and an operator that does
not apply to the value type are rejected when the filter is defined. The
request names the key; the column comes only from the definition.

```
?filter[age][gte]=18&filter[age][lt]=65    several operators: AND
?filter[status]=active                      eq
?filter[status][]=active&filter[status][]=on_leave    in (a plain list)
?filter[status][in]=active,on_leave         in (comma-separated)
?filter[salary][between]=3000,5000          between
?filter[manager][null]=1                    null / not_null take no value
?filter[reports][has]=1                     relation existence
?filter[status]=                            empty value: no filter (a form's "All")
```

Unknown keys and operators return 400 in strict mode (the default); invalid
values always return 400. Messages name the parameter, never the value.

## Value types (`Gcego\Data\Filters\Types`)

| Type | Accepts | Operators |
| --- | --- | --- |
| `StringType` | up to 255 characters, no control characters | equality, lists, null checks, text |
| `Integer` | `-?\d{1,18}` | equality, lists, null checks, ranges |
| `Decimal` | plain decimals (no exponent) | equality, lists, null checks, ranges |
| `Boolean` | `true`, `false`, `1`, `0` | equality, lists, null checks |
| `Date` | `YYYY-MM-DD` (a valid date) | equality, lists, null checks, ranges |
| `DateTime` | `YYYY-MM-DD HH:MM[:SS]` or with `T`; normalized to `Y-m-d H:i:s` | equality, lists, null checks, ranges |
| `Time` | `HH:MM[:SS]`; normalized to `H:i:s` | equality, lists, null checks, ranges |
| `Enum::make(Status::class)` | the backed enum's values | equality, lists, null checks |
| `Uuid` | UUIDs, normalized to lowercase | equality, lists, null checks |
| `Json` | a scalar element of a JSON array column | `Contains`, null checks |

"Array" filtering is `Json` with `Contains` (`whereJsonContains`): a JSON
array column contains the value. Implement `Contracts\FilterValueType` for
your own types.

## Operators (`Gcego\Data\Filters\Operators`)

| Operator | Request | Values | Applies to |
| --- | --- | --- | --- |
| `Equal`, `NotEqual` | `eq`, `neq` | one | every type except `Json` |
| `GreaterThan`, `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual` | `gt`, `gte`, `lt`, `lte` | one | ordered types |
| `Between` | `between` | exactly two (inclusive) | ordered types |
| `In`, `NotIn` | `in`, `not_in` | 1 to `filters.max_values` (100) | every type except `Json` |
| `Contains`, `NotContains`, `StartsWith`, `EndsWith` | `contains`, `not_contains`, `starts_with`, `ends_with` | one | text (`Contains` also `Json`) |
| `IsNull`, `IsNotNull` | `null`, `not_null` | none | every type |
| `Has`, `DoesntHave` | `has`, `doesnt_have` | none | relation paths (Eloquent) |

Each operator carries its own SQL and in-memory behaviour, so database and
collection sources agree. **SQL semantics:** `neq` and `not_in` do not match
NULL; text operators are case-insensitive (ILIKE on PostgreSQL, the column
collation on MySQL/MariaDB) and match `%` and `_` literally.

## Custom operators and custom filters

```php
final readonly class JsonLength implements Operator
{
    public function name(): string { return 'json_length'; }
    public function arity(): int { return 1; }
    public function supports(FilterValueType $type): bool { return $type instanceof Integer; }
    public function apply(EloquentBuilder|QueryBuilder $query, string $column, mixed $value, FilterValueType $type): void
    {
        $query->whereJsonLength($column, $value);
    }
    public function matches(mixed $actual, mixed $value, FilterValueType $type): bool
    {
        return is_array($actual) && count($actual) === $value;
    }
}

Filterable::make('tags', [Integer::make(), new JsonLength]);   // ?filter[tags][json_length]=3
```

A custom operator receives a trusted column and a validated, cast value, and
must bind it. A `CustomFilter` handler receives only the validated value
(one value, or a list when `In` is declared) inside the request filters' own
AND group, so it can narrow but never widen the mandatory scope.
