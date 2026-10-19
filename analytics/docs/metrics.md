# Metrics

| Kind | Definition |
| --- | --- |
| count | `new Metric('attempts', AssessmentResult::class)` or `dataset: '…'` |
| count_distinct, sum, avg, min, max | `aggregate: 'avg', field: 'score'` |
| ratio | `aggregate: 'ratio', numerator: 'completed', denominator: 'attempts'` |
| percentage | the same, × 100 |
| rate | the same, × `per` (default 1000) |
| derived | `aggregate: 'derived', formula: '({passed} + {merit}) / {sat} * 100'` |

## Values, nulls and precision

| Situation | Result |
| --- | --- |
| Counts and sums of nothing | `0` |
| Averages and extremes of nothing | `null` (shown "—") |
| A composite with a missing operand, or a zero denominator | `null` |
| Float results | Rounded to `precision` (default 2; counts 0) |
| Groups smaller than the dataset's minimum size | `null`, with `suppressed: true` |

## Options

- **`filters`:** trusted equality or `in` conditions on declared fields or dimensions.
- **`dateColumn` / `interval`:** the default time series.
- **Display:** `format` (number, percentage, currency, duration, …), `unit`, `currency`.
- **`fill`:** what empty time buckets hold.
- **`cache`:** the cache lifetime in seconds; 0 disables caching.

## Composite metrics

Composite metrics calculate every metric they use with the same period, filters and viewer. They need every one of those metrics to be visible. Cycles are refused when the definition is validated and when it is calculated.

## Formula grammar

```
expr := term (('+' | '-') term)*      term := factor (('*' | '/') factor)*
factor := number | '{' metric-name '}' | '-' factor | '(' expr ')'
```

Anything else is a definition error. Formulas come from definitions only, never from requests.
