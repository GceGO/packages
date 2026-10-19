# Sidebar, top, mobile navigation and Livewire

## Sidebar

| Feature | Details |
| --- | --- |
| Markup | `<nav aria-label>`, nested lists, collapsible groups, icons, badges, headings and dividers. |
| Compact mode | A toggle (`sidebar.compact_toggle`) collapses labels to icons. Labels stay available to screen readers. The preference is kept in `localStorage` per navigation, and `sidebar.default_compact` sets the start. |
| Size | Links are at least 40px tall (touch targets) and truncate long labels. |
| Shell | The panel shell owns the sidebar's width, scrolling and placement; this package renders its content. |

## Top navigation

`display="topbar"`: links and dropdowns in a row that wraps (it never
clips dropdowns with overflow). Use the mobile drawer on small screens.

## Mobile drawer

`display="mobile"`: a trigger button (`aria-expanded`, `aria-controls`)
and a modal dialog (`role="dialog" aria-modal="true"`, labelled).

| Behavior | Details |
| --- | --- |
| Opening | Focus moves to the active link, or the first one. |
| Focus trap | Tab and Shift+Tab stay inside the drawer. |
| Closing | Escape, the close button, the backdrop or choosing a link closes it, and focus returns to the trigger. |
| Scroll lock | `overflow-hidden` on `<html>` while it is open. |

Panel shells control it with window events, so they don't need its
internals:

```js
window.dispatchEvent(new CustomEvent('gcego-navigation-open', { detail: { id: 'navigation:admin' } }))
// also gcego-navigation-close and gcego-navigation-toggle; without an id, every drawer responds
```

Showing the trigger only on small screens (for example inside an
`lg:hidden` wrapper) is the shell's choice.

## Livewire

| Topic | Details |
| --- | --- |
| Links | Ordinary links; no item is a Livewire component. `links.wire_navigate` adds `wire:navigate` to internal links for SPA navigation. |
| Updates | Inside a Livewire component, re-renders keep the active page: the page is resolved from Livewire's original URL. |
| Browser state | Open groups and dropdowns survive morphing (`wire:ignore.self` on toggles and lists). The Alpine.js state is self-contained `x-data` expressions, so morphed-in HTML works and listeners are removed with their element. |
