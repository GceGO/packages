# Statistics

## Stat

```php
new Stat('Total Students', StudentCountProvider::class)
new Stat('Revenue', new Aggregate(Payment::class, 'sum', 'amount', date: 'paid_at', compare: true), new Trend(), new Property(Stat::Format, 'currency'))
```

| Property | Meaning |
| --- | --- |
| `Label`, `Description`, `Icon` | card text and a trusted icon |
| `Value` | a static value or a provider class (or use `Value`, `DataProvider`, `Aggregate` nodes) |
| `Format` | `number`, `decimal`, `percent` (value is a fraction; `PercentScale` 100 for 0–100), `currency`, `compact` (1.5K), `duration` (seconds), `ratio` |
| `Decimals`, `Currency` (ISO 4217; default `formatting.currency`, XAF), `Prefix`, `Suffix` | formatting |
| `Tone` | icon accent: default, success, warning, danger, info, neutral |
| `Url` | a details link (http(s) or root-relative) |

The value renders in `<data value="…">`. Null values show the empty state.

## Trends

`new Trend(increase: 'positive' | 'negative' | 'neutral', show: 'percent' | 'absolute' | 'both', label: …)`
compares the value with the previous one:
- from an `Aggregate` with `compare: true`, which uses the previous period of the same length;
- or from a provider's `WidgetDataResult::value($value, previous: …)`.

The caption spells out direction, amount and meaning ("Up 12% from the
previous period. This is an improvement."), so the meaning never depends
on color or arrows. An increase is not automatically good.

## Stats

`new Stats(new Stat(...), ...)` is a responsive grid of equal-height cards.

| Rule | Details |
| --- | --- |
| Data | Each stat keeps its own data source. A `DataProvider` on the group may return `WidgetDataResult::values(['name' => …])` for stats without one. |
| Requirements | They are checked per stat, so an unauthorized stat is simply absent. |
| Name | A group without a Name or Label is named after its stats. |
| Columns | `Columns` (1–4) defaults to the number of stats. |
