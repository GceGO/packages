# Responsive layouts

## Breakpoints

Breakpoints are Tailwind's, mobile first: `default` (every width), `sm`
(40rem), `md` (48rem), `lg` (64rem), `xl` (80rem), `2xl` (96rem).

A value set at a breakpoint applies from that width up, until a larger
breakpoint overrides it. Larger breakpoints always win from their width
up.

## Responsive values

These accept a single value or a breakpoint map:

| Accepts a breakpoint map | Example |
| --- | --- |
| `Columns` | `new Columns(['md' => 2, 'xl' => 4])` |
| `ColumnSpan` | `new ColumnSpan(['default' => 'full', 'lg' => 1])` |
| `Gap` | `new Gap(['default' => 4, 'lg' => 8])`; also `new Gap(row: 8, column: 4)` |
| `Align` | `new Align(items: ['default' => 'stretch', 'md' => 'center'], justify: 'between')` |
| `Spacer` size | `new Spacer(['default' => 4, 'lg' => 12])` |

`Breakpoint::parse()` and `ResponsiveValue::of()` reject the following,
with a message that names the value:
- unknown breakpoints (`'phone'`);
- lists instead of maps;
- empty maps;
- out-of-scale values.

## Bounded scales

| Value | Allowed |
| --- | --- |
| Columns | 1–6 |
| Spans | 1–6, `full`, `auto` |
| Gaps | 0, 1, 2, 3, 4, 6, 8, 10, 12 (4 = 1rem) |
| Spacers | 2, 4, 6, 8, 12, 16 |
| Align items | start, center, end, stretch, baseline |
| Align justify | start, center, end, between, around, evenly |

Every class a layout can emit is a literal in `Presets\LayoutPresets` (or a
view), so Tailwind finds all of them at build time. Values are looked up in
these maps, never concatenated at runtime, and nothing comes from the
browser.

For all GCEGO packages together, the compiled stylesheet is 54.4 KB
minified (9.5 KB gzipped).

## Visibility

```php
new Stat('Trend', …, new Visibility(hiddenBelow: 'md'))   // from md up only
new Card(…, new Visibility(hiddenFrom: 'lg'))             // below lg only
```

| Rule | Details |
| --- | --- |
| Who accepts it | `Visibility` is a placement configuration, applied by the parent layout (one wrapper). |
| Presentational only | Hidden content is still rendered and its data loaded, so it is never a security boundary. Use requirements to withhold content. |
| Validation | It refuses to hide a child everywhere. |
