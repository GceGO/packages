# Rendering

| Entry point | Mode |
| --- | --- |
| `InfolistManager::render($definition, $key)` | static Blade |
| `InfolistManager::renderRecord($resolved, $record)` | static, for a record the caller already authorized |
| `<x-gcego-infolists::infolist definition="…" :record="…" />` | static Blade component |
| gcego/runtime blueprint render with `metadata: ['record' => $id]` | static in Static mode; mounts `gcego-infolist` in Livewire mode |
| `<livewire:gcego-infolist>` | interactive, with record actions |

A bare `Infolist` rendered without a blueprint and record throws an
`InvalidEntryException` (a `RenderException` through the runtime).

## Markup

- `<article>` with an optional `<h2>`.
- Entries are `<div><dt>…</dt><dd>…</dd></div>` inside `<dl>` grids.
- Sections are `<section aria-labelledby>`.
- Dates use `<time datetime>`.
- Images have alt text and `loading="lazy"`.
- Badges and booleans always include text.
- Long values wrap (`overflow-wrap:anywhere`), and grids collapse to one column on small screens.

Views can be overridden in `resources/views/vendor/gcego-infolists`
(publish tag `gcego-infolists-views`).

## States

| State | Behavior |
| --- | --- |
| Empty | Placeholder for empty values; "No information to show." when nothing is visible. |
| Unavailable | In Livewire, a record that disappears or becomes invisible shows "This record is not available." |
| Errors | Static renders raise 404 or 403 like any gcego/security lookup. Nothing from the exception is printed. |
| Loading | No loading UI for static renders. Livewire hosts provide a lazy-loading placeholder. |
