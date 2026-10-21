# Groups, dropdowns, headings and dividers

```php
new Group('Academic Management', new Property(Group::Icon, 'academic-cap'),
    new Item('Students', 'admin.students.index'),
    new Group('Assessments', new Item('Exams', 'admin.exams.index')),   // nested
)
new Group('Administration', new Property(Group::Collapsed, true), …)
new Group('Help', new Property(Group::Collapsible, false), …)       // a plain labelled section
```

## Groups

| Topic | Details |
| --- | --- |
| Sidebar | A section whose header button toggles its list (`aria-expanded`, `aria-controls`). It starts expanded unless `Collapsed`, and always expanded when it holds the active page. Expanding and collapsing happen in the browser (Alpine.js): no requests. Livewire updates keep the state. |
| Collapsible | `groups.collapsible` sets the default; `Collapsible` overrides it per group. Non-collapsible groups show a label (`aria-labelledby` on the list). |
| Hiding | A group the user can see nothing in (no visible link or group inside) is not shown. Neither are its headings and dividers. |
| Depth | Nesting is limited by `groups.max_depth` (default 3); deeper groups fail when the tree is built. |
| Cycles | Cycles (only possible with hostile component implementations) are refused by gcego/components traversal. |

## Dropdowns

```php
new Dropdown('Reports', new Property(Dropdown::Align, 'end'), new Item(…), new Group('Finance', new Item(…)))
```

| Topic | Details |
| --- | --- |
| Top navigation | Every group and dropdown is a disclosure: a button (`aria-expanded`, `aria-controls`) and a panel of ordinary links. It is not an ARIA menu. Nested groups become a heading and their links. |
| Keyboard | ArrowDown and ArrowUp on the button open it and focus the first or last link; in the panel, the arrows move (wrapping), and Home and End jump. |
| Closing | Escape closes and returns focus to the button. Clicking outside, or tabbing away, closes it. `Align`: start or end. |
| Sidebar | A dropdown is a group that starts collapsed. |

## Headings and dividers

```php
new Heading('Planning')
new Divider
```

| Node | Details |
| --- | --- |
| Heading | A non-clickable label in the list. A heading with nothing visible after it is not shown. |
| Divider | Decorative (`aria-hidden`). Dividers at the edges or next to each other are not shown. |
