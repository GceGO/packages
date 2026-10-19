# Layouts

| Layout | Use |
| --- | --- |
| `Section` | `new Section('Title', ...)`: heading (`<h3>`, `aria-labelledby`), `Description`, `Icon`, `Columns`, `Collapsible`, `Collapsed` (button with `aria-expanded`/`aria-controls`) |
| `Grid` | `new Grid(2, ...)`: 1 to 4 columns, one column on small screens; entries use `ColumnSpan` |
| `Tabs` / `Tab` | `new Tabs(new Tab('Overview', ...), new Tab('Academic', ...))`: WAI-ARIA tabs with roving tabindex, arrow keys, Home and End; switching is client-side |
| `Split` | `new Split($main, $aside)`: two columns from `From` (md/lg) up, with `AsideWidth` (sm/md/lg), stacked below; the aside is `role="complementary"` |

## Grouping

Consecutive entries share a `<dl>` grid, and layouts render between them.

## Empty content

A layout whose content renders nothing (hidden, unauthorized or empty
entries) renders nothing, including its heading. Tabs without content are
dropped.

## Conditions and permissions

```php
new Section('Archive', new VisibleWhen('status', 'archived'), ...)
new Section('Grading', VisibleWhen::filled('grading_scheme'), ...)
new Section('Pay', new Permission('staff.pay.view'), ...)                 // gcego/security requirement
new Text('notes', VisibleWhen::using(fn (Subject $s) => $s->isLate()))
```

| Rule | Details |
| --- | --- |
| Evaluated on the server | Conditions are checked against the authorized record. |
| No access granted | A condition on a path the user may not read is false. |
| Requirements | They are checked for the current user and record. |
| Nothing resolved | The entries of a hidden layout are never resolved. |
