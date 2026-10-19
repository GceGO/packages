# Shells, branding and accessibility

## Layouts

| Layout | View | Notes |
| --- | --- | --- |
| `sidebar` | `gcego-panels::shells.sidebar` | sticky `aside` (`lg:w-72`); branding, navigation, switcher; collapsible |
| `compact` | `gcego-panels::shells.compact` | the sidebar layout, `lg:w-60` |
| `topbar` | `gcego-panels::shells.topbar` | branding, the navigation's `topbar` display, switcher and user menu in one bar |

Below `lg`, every layout shows a top bar with gcego/navigation's `mobile` display. That is a modal drawer with a focus trap, Escape and a backdrop.

`gcego-panels::document` replaces gcego/pages' layout:

- on panel routes it renders the panel shell;
- elsewhere it includes `gcego-pages::layouts.app`.

Publish the views (`--tag=gcego-panels-views`) to change the markup.

## Accessibility (WCAG 2.2 AA)

- a "Skip to content" link to the `main` element, which takes focus (`tabindex="-1"`);
- one `main`, a labelled `aside`, a `header` and a `footer`; navigations are labelled by gcego/navigation;
- menus (switcher, user menu) are native `<details>`, so keyboard and screen-reader support needs no script, and Alpine adds Escape and outside-click closing;
- the sidebar toggle has `aria-controls` and `aria-expanded`;
- the current panel in the switcher has `aria-current="page"`;
- targets are at least 40px tall, focus rings are visible (`focus-visible:outline-2`), and motion respects `prefers-reduced-motion`;
- logos are decorative (`alt=""`) because the name is always rendered as text.

## Branding

| Property | Rule |
| --- | --- |
| `Name`, `Tagline` | escaped text (120 and 200 characters) |
| `Logo`, `LogoDark`, `CompactLogo`, `Favicon`, `HomeUrl` | root-relative (`/images/logo.svg`) or `https://` URLs; no `javascript:`, `data:`, protocol-relative or quote characters |

The dark logo is shown with `.dark` (gcego/chroma's appearance controller).

## Styling

The slots are those of `Panel::styleSlots()`. Override them per panel with `new Tailwind(new Slot(Panel::SidebarSlot, [...]))`, or globally with `ChromaManager::override(Panel::class, ...)`.

The shell's classes are in `Presets\PanelPresets`, which gcego/chroma's `gcego.css` scans. With panels, the production stylesheet measured 57.4 KiB against a 60 KiB budget.

`Panel::Theme` sets `data-gcego-theme` on `<html>` and includes the theme's tag.
