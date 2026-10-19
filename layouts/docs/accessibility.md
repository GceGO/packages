# Accessibility

gcego/layouts targets WCAG 2.2 AA for the structure it renders. The
components inside a layout own the accessibility of their own content.

| Topic | What the package does |
| --- | --- |
| Headings | Titled layouts, sections and cards render a heading at the current level, and their children go one level deeper (capped at h6). The root heading level comes from the render (1 by default; blueprints start at 1). |
| Landmarks and regions | Titled sections and cards are `<section aria-labelledby>`. `Aside` is a labelled `<aside>`. `Main` can be `<main>` (one per page; the inspector reports two). `ScrollArea` is a labelled, focusable region. |
| Keyboard | Tabs follow the WAI-ARIA tabs pattern (arrows, Home, End, roving tabindex). Accordions, collapsible regions and collapsible sections are native `<details>` (Enter, Space). Scroll areas are focusable for arrow-key scrolling. |
| Focus | Tabs, summaries, panels and scroll areas show a `focus-visible` outline in the gcego ring color; where the browser default is reset (summaries), it is replaced by that outline. |
| Reading order | DOM order is the source order. No CSS reordering (no `order-*`; grid `Dense` is opt-in and reported by the inspector). Visual, keyboard and screen-reader order match. |
| Zoom and reflow | Widths and gaps use relative units (rem; a test checks the presets contain no px). Grids collapse to one column and splits stack on narrow screens. Columns are `minmax(0, …)` with `min-w-0` regions, so content reflows at 400% zoom without horizontal page scrolling. |
| Status | `Loading` is a polite `role="status"` with a screen-reader text ("Loading…"); its skeleton is `aria-hidden`, and its pulse respects reduced motion. |
| Decoration | Default dividers and spacers are `aria-hidden`. A non-decorative divider is an `<hr>` (or a labelled `role="separator"`), and icons are `aria-hidden`. |
| Contrast and color | Text and borders use gcego tokens, checked for contrast by gcego/chroma. Card tone variants are never the only signal. |

`LayoutInspector` warns about:
- unlabelled scroll areas;
- several unlabelled asides;
- single-tab tab sets;
- dense grids;
- vertical dividers outside rows.

It reports two `<main>` elements as an error.
