# Datasets and dimensions

```php
new Dataset('assessment-results', AssessmentResult::class,
    new Ability('analytics.view'),                       // who may use the dataset (required)
    new Field('score', 'Score', type: 'number'),
    new Field('time_spent', type: 'integer', aggregates: ['sum', 'avg']),
    new Field('email', type: 'string', sensitive: true, new Ability('students.contact')),
    new Dimension('subject_id', 'Subject', labelModel: Subject::class),
    new Dimension('level', 'Level', options: ['o' => 'Ordinary Level', 'a' => 'Advanced Level']),
    new Dimension('institution_id', 'Institution', new Ability('analytics.cross-institution')),
    dateColumn: 'submitted_at',
    minimumGroupSize: 5,
    scope: StudentsSeeTheirOwn::class,
);
```

## Fields

The `type` sets which aggregates a field allows; `aggregates` narrows them further.

| Type | Aggregates |
| --- | --- |
| `number`, `integer` | All aggregates |
| `date`, `datetime` | count, count_distinct, min, max |
| `string`, `boolean` | count, count_distinct |

Other options:

- **`filterable: false`** hides the field from filters.
- **`sensitive: true`** makes the field usable only by viewers who pass its requirements. A sensitive field is never filterable or a dimension, and report columns on it are hidden from other viewers.

## Dimensions

Dimensions group and filter by a column (`column:` defaults to the name). Their labels come from one of three sources:

- **`options`:** a key ⇒ label map. These keys are also the only accepted filter values.
- **`labelModel` / `labelColumn`:** one `whereIn` query on that model, run through gcego/data as the viewer. Records the viewer cannot see (other tenants, policies) get no label.
- **`labels:`:** a trusted `DimensionLabels` resolver.

Null keys group as "Not set". A dimension with requirements is available only to viewers who pass them.

## Record scopes and providers

- **`AnalyticsScope::apply($query, $context)`** narrows rows inside its own where group, after gcego/data's scopes. It can only narrow, never widen.
- **`AnalyticsProvider::execute(QueryPlan $plan, AnalyticsContext $context): AnalyticsRows`** serves data that isn't an Eloquent model. It must apply the tenant and access rules itself. It can call `EloquentAnalyticsExecutor::aggregateOn($scopedBuilder, $plan, $context)` to reuse the same safe aggregation; see `Integration\Workflows\WorkflowAnalyticsProvider`.

## Academic data

Analytics ships no built-in academic metrics: GCEGO's own schema decides what is measurable.

- **Levels:** declare datasets on your assessment, practice and progress models with dimensions such as `level` (Ordinary/Advanced) and `subject_id`. Keep levels apart with filters or an `AnalyticsScope`; for example, a student scope can restrict rows to the student's active level and subjects.
- **Scores:** measure them only where grading data exists. Essays and structured answers without numeric marks should be counted (submissions, completions), not averaged. Averages ignore null scores.
