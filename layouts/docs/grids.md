# Grids, rows, stacks and containers

## Grid

```php
new Grid(new Stat('Students', …), new Stat('Teachers', …), new Stat('Subjects', …))
new Grid(new Columns(3), …)
new Grid(new Columns(['default' => 1, 'md' => 2, 'xl' => 4]), new Gap(['default' => 4, 'lg' => 8]), new Align(items: 'start'), …)
new Grid(new Property(Grid::MinItemWidth, 'sm'), …)
```

| Columns | Classes |
| --- | --- |
| none (auto, from the children rendered, at most 4) | 3 children: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` |
| `new Columns(1)` | `grid-cols-1` |
| `new Columns(2)` | `grid-cols-1 sm:grid-cols-2` |
| `new Columns(3)` / `(4)` | 1, 2 from sm, the count from lg |
| `new Columns(5)` / `(6)` | 1, 2 from sm, 3 from md, the count from xl |
| `new Columns([...])` | exactly the map; 1 column below its first breakpoint |

Other options:

| Option | Effect |
| --- | --- |
| `MinItemWidth` (`xs` 12rem, `sm` 16rem, `md` 20rem, `lg` 24rem) | An auto-fit grid: as many columns as fit, never wider than the screen. |
| `Dense` | Fills holes left by spanning children. This changes the visual order, so use it only for independent cards; the inspector warns about it. |
| `Element` | `div` or `section`. |

Columns are 1 to 6. Wider designs use `Split` ratios or nested grids.

## Column spans

```php
new Stat('Revenue', …, new ColumnSpan(2))
new LineChart(…, new ColumnSpan(['default' => 'full', 'xl' => 2]))
new Stat('Totals', …, new Property(Stat::Span, 'full'))   // a widget's Span metadata, used when it has no ColumnSpan
```

| Rule | Details |
| --- | --- |
| Values | 1 to 6, `full` or `auto`. |
| Who accepts it | `ColumnSpan` is a placement configuration, so every component accepts it, including widgets, tables and plain components. |
| Overflow | At each breakpoint the span is limited to the columns there, so spans never add columns or overflow. On a one-column phone layout everything is full width. |
| Validation | A span wider than the most columns of an explicit `Columns` fails validation. |
| Wrappers | A child with a span or `Visibility` is wrapped in one `<div>` carrying those classes. Every other child is output as is. |
| Ignored | Spans outside a grid, and numeric spans in an auto-fit grid, are ignored (the inspector warns). |

## Row

```php
new Row(new Align(justify: 'between'), new Stat(…), new Stat(…))
new Row(new Property(Row::StackBelow, 'md'), …)   // stacked on phones, a row from md
new Row(new Property(Row::Wrap, false), …)
```

| Option | Default |
| --- | --- |
| Gap | 3 |
| Alignment | Children are centred vertically. While stacked they are stretched, and centred from the breakpoint. |
| `Element` | `div`; also `nav`, `header`, `footer`. |

## Stack

```php
new Stack(new Gap(['default' => 4, 'lg' => 6]), …)
new Stack(new Property(Stack::Divided, true), …)   // a rule between children instead of a gap
```

Default gap: 4. `Element`: `div`, `section` or `article`.

## Container

```php
new Container(new Property(Container::MaxWidth, '5xl'), new Property(Container::Element, 'main'), …)
new Container(new Property(Container::Fluid, true), new Property(Container::Padding, 'none'), …)
```

| Option | Values |
| --- | --- |
| `MaxWidth` | `sm` … `7xl`, `prose`, `full`; default `container.default_max_width` (`7xl`) |
| `Padding` | `none`, `sm`, `md` (default: `px-4 sm:px-6 lg:px-8`), `lg` |
| `Centered` | default true |
| `Element` | `div`, `section`, `article`, `main` (only when the page has no other), `header`, `footer` |

## Layout

The root `new Layout('name', …)` arranges its children in a vertical flow
(gap from `grid.default_gap`, 6). With a Label it shows the title at the
current heading level, so its sections' headings go one level deeper.
