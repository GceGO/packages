# Split, sidebar, sticky and scroll layouts

## Split

```php
new Split(
    new Main(new Table(new Text('name', 'Name'))),
    new Aside('Recent activity', new ActivityFeed('Activity', new ActivitySource(ActivityLog::class, title: 'description', time: 'created_at'))),
)
new Split(new Property(Split::Ratio, '3:1'), new Property(Split::StackBelow, 'xl'), new Main(…), new Aside(…))
```

| Rule | Details |
| --- | --- |
| Children | Exactly one `Main` and one `Aside`, in either order. |
| Ratio | First region : second region: `1:1`, `2:1` (default), `3:1`, `3:2`, `1:2`, `1:3`, `2:3`. |
| Stacking | Side by side from `StackBelow` (`md`, `lg` (default), `xl`), stacked below it. |
| Overflow | Columns are `minmax(0, 1fr)` and regions `min-w-0`, so wide content (tables) scrolls inside its region instead of widening the page. |
| Order | Visual, keyboard and screen-reader order are the source order. Declare the Aside first to put it on the start side. Nothing is reordered with CSS. |
| One region left | If one region renders nothing, the other takes the full width. |

## Main and Aside

| Region | Renders as |
| --- | --- |
| `Main` | `<div>` by default. `Element` can be `main` (only when the page has no other `<main>`; the inspector reports two), `section` or `article`; a section or article is labelled by its Label. |
| `Aside` | An `<aside>`, a complementary landmark labelled by its Label. Give each aside a Label when there are several. |

`new Property(Aside::Sticky, true)` keeps the aside in view while the main
region scrolls, from the breakpoint where they sit side by side.

## Sidebar

```php
new Sidebar(new Aside('Filters', …), new Main(new Table(…)))
new Sidebar(new Property(Sidebar::Width, 'lg'), new Property(Sidebar::Sticky, true), new Main(…), new Aside(…))
```

A fixed-width side column (`Width`: `sm` 16rem, `md` 18rem (default),
`lg` 20rem, `xl` 24rem) beside fluid main content.

| Option | Details |
| --- | --- |
| Placement | Source order: declare the Aside first for a start-side sidebar. |
| `StackBelow` | `md`, `lg` (default), `xl`. |
| `Sticky` | Sticks the side column. |

This is a structural sidebar; navigation menus belong to gcego/navigation.

## Sticky

```php
new Sticky(new Row(new Actions(…)))                                       // stuck to the top
new Sticky(new Property(Sticky::Position, 'bottom'), …)                   // a bottom action bar
new Sticky(new Property(Sticky::Offset, 16), new Property(Sticky::From, 'lg'), …)
```

| Option | Values |
| --- | --- |
| `Offset` | 0, 2, 4, 6, 8, 12, 16. Use it to clear fixed navigation (16 = 4rem). |
| `Layer` | `base` (z-10), `raised` (z-20), `overlay` (z-30). Stays below dialogs. |
| `From` | Sticky only from a breakpoint. |
| `Surface` | Default true: a translucent surface background, so content scrolling under it stays readable. |

Limitations (CSS `position: sticky`):
- It sticks within its nearest scrolling ancestor, and only while its
  parent is in view.
- An ancestor with `overflow: hidden` stops it.

## ScrollArea

```php
new ScrollArea('Audit log', new Property(ScrollArea::MaxHeight, 'lg'), new ActivityFeed(…))
new ScrollArea('Timetable', new Property(ScrollArea::Direction, 'horizontal'), …)
```

| Option | Values |
| --- | --- |
| `Direction` | `vertical` (default), `horizontal`, `both` |
| `MaxHeight` | `sm` 16rem, `md` 24rem (default), `lg` 32rem, `xl` 40rem, `none` |

Behavior:
- **Native scrolling.** No scrollbar JavaScript.
- **Keyboard.** It is focusable (`tabindex="0"`, with a visible focus
  ring), so keyboard users can scroll it with the arrow keys.
- **Labelled.** It is a region labelled by its Label ("Scrollable content"
  by default; give it a real one).
