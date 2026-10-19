# Layouts

Layouts arrange fields. They hold no state and are invisible to validation,
except that a `Wizard` defines steps.

| Layout | Shorthand | Notes |
| --- | --- | --- |
| `Section` | `new Section('Basic Information', ...)` | a `<section>` with a heading (`aria-labelledby`) and an optional description |
| `Grid` | `new Grid(2, ...)` | 1–4 columns (single column on small screens); fields set `ColumnSpan` |
| `Tabs` / `Tab` | `new Tabs(new Tab('General', ...), new Tab('Advanced', ...))` | WAI-ARIA tabs with arrow, Home and End keys. Every panel stays in the DOM and is submitted. |
| `Wizard` / `Step` | `new Wizard(new Step('Basics', ...), new Step('Details', ...))` | one top-level wizard per form; see below |

Layouts take `VisibleWhen` too: hiding a layout hides every field inside it,
for rendering and for validation.

## Wizard steps

| Behavior | Details |
| --- | --- |
| One step at a time | Only the current step is rendered. |
| Next validates | "Next" validates only the current step's fields, on the server. |
| Submit validates everything | Submit validates every step. On errors, the host goes back to the first step that has one. |
| Step is locked | The step number is `#[Locked]`; the client cannot skip ahead. |
