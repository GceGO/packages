# Data providers

A widget has one data source:

| Source | Use |
| --- | --- |
| `Value` property / `new Value(12)` | static values |
| `fn (WidgetDataContext $context) => …` / `new Value(fn …)` | trusted closures, evaluated only when rendering |
| `new DataProvider(Provider::class)` / `new Stat('Label', Provider::class)` | a `Contracts\WidgetDataProvider` from the container |
| `new Aggregate(...)` | a database aggregate through gcego/data ([aggregation](aggregation.md)) |
| `new ActivitySource(...)` | records as activity items (`ActivityFeed`) |

```php
final class StudentEnrolmentProvider implements WidgetDataProvider
{
    public function resolve(WidgetDataContext $context): WidgetDataResult
    {
        $months = $context->aggregate(Enrolment::class, new Aggregation(dateColumn: 'enrolled_at', bucket: TimeBucket::Month));

        return WidgetDataResult::series('Enrolments', $months->pairs());
    }
}
```

## WidgetDataContext

**Properties:**

| Property | Meaning |
| --- | --- |
| `widget` | the identity |
| `path` | the child path |
| `user`, `tenant` | the current user and tenant |
| `period` | the selected range (from and to in the storage zone) |
| `record` | from the host |
| `page`, `locale`, `timezone` | feed page and display settings |

**Helpers** (gcego/data, as the user, so policies, tenant scopes and
mandatory constraints apply):

| Helper | Does |
| --- | --- |
| `count($modelOrSource)` | a count |
| `aggregate($modelOrSource, Aggregation, ?Period)` | an aggregate; applies the period when the aggregation has a date column |
| `query(Source, $input)` | a page of records |

The context is built per request and never serialized.

## WidgetDataResult

| Constructor | Used by |
| --- | --- |
| `value($value, previous: …)` | stats (and their trends) |
| `progress($value, $maximum)` | progress and goals |
| `series($label, [label => value])`, `chart($labels, $datasets)` | charts |
| `items(list<ActivityItem>, hasMore)` | feeds |
| `values(['name' => …])` | shared group providers |
| `empty()` | no data |

## Errors

| Situation | What happens |
| --- | --- |
| The provider throws | The widget shows "This widget could not be loaded." (with Retry in Livewire), and the exception is reported. Messages never reach users. |
| The data is unauthorized (`AuthorizationException`) | The widget is hidden. |
