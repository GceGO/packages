# Accessibility

The package targets WCAG 2.2 AA.

| Topic | What the package does |
| --- | --- |
| Landmarks | Every display is a `<nav>` labelled by the Navigation's Label ("Main navigation" by default); breadcrumbs are `<nav aria-label="Breadcrumb">`. |
| Lists | Nodes are list items (`role="list"` keeps list semantics where list styles are removed). Groups' lists are labelled by their header. |
| Current page | `aria-current="page"` on the one active link and on the current breadcrumb. |
| Disclosure, not menus | Groups and dropdowns are buttons with `aria-expanded` and `aria-controls` controlling ordinary link lists. No `role="menu"` or `menuitem` (a test asserts it), because this is site navigation, not an application menu. |
| Keyboard | Group toggles are native buttons (Enter, Space). Dropdowns: Arrow keys, Home, End, Escape (focus returns to the trigger), Tab away closes. The mobile drawer traps focus, closes with Escape and restores focus. Every behavior is executed in tests (Node). |
| Focus | Visible `focus-visible` outlines in the gcego ring color on links, toggles and triggers. |
| Screen readers | Badges read their meaning ("3 pending requests"). New-tab links say "(opens in a new tab)". Icon-only controls have text ("Open navigation", "Close navigation", "Show icons only"). Compact mode hides labels visually only. Decorative icons, separators and dividers are `aria-hidden`. |
| Touch and zoom | Links are at least 40px tall, group toggles 36px, mobile controls 44px: all above the 24px minimum of WCAG 2.2. Labels truncate rather than overflow. The top navigation wraps. Sizes are in rem. |
| Contrast and color | gcego tokens; badge variants pair color with text. Active links use both a tint and the primary color. |

`NavigationInspector` warns about links with the same label.
