# Livewire

```blade
<livewire:gcego-widget widget="enrolment" />
<livewire:gcego-widget widget="student_summary" :record="$student->id" />
<livewire:gcego-widget widget="layout:dashboard" path="dashboard.trends.enrolment" />   {{-- embedded; the renderer mounts it --}}
<x-gcego-widgets::widget name="enrolment" />       {{-- picks Livewire only when the widget needs it --}}
```

## WidgetHost state

| Property | Kind | Notes |
| --- | --- | --- |
| `widget`, `record`, `path`, `page`, `notice` | `#[Locked]` | `widget` is the owner identity when `path` is set |
| `range`, `from`, `to` | untrusted | validated against the widget's DateRange on every request; invalid values reset with a notice |

## Behavior

| Topic | Details |
| --- | --- |
| Methods | `refresh()`, `olderPage()`, `newerPage()` (bounded at 100 pages). |
| Every request | Resolves the widget, checks its requirements (a 403 after access is revoked), resolves the period and reads data as the current user and tenant. |
| Snapshot | Only these small values; never data or models. |
| Polling and events | `wire:poll.<n>s.visible` for `Refresh(every:)`; `getListeners()` maps `Refresh(on:)` events to `refresh`. |
| Loading | A loading overlay with `role="status"`; `aria-busy` during requests; a pulse placeholder for lazy mounting. |
| Charts | Re-keyed on data change, so the old chart is destroyed and a new one built. |
