# Tabs, accordions and collapsible regions

## Tabs

```php
new Tabs(new Property(Tabs::Label, 'Student'),
    new Tab('Profile', new Infolist(…)),
    new Tab('Results', new Property(Tab::Icon, 'chart'), new Table(…)),
    new Tab('Fees', new Permission('fees.view'), …),
)
new Tabs(new Property(Tabs::Active, 'results'), …)
```

The pattern is the WAI-ARIA tabs pattern with automatic activation.

**Markup:**
- `role="tablist"`, labelled by the Tabs Label;
- each tab is a `<button role="tab">` with `aria-selected`, `aria-controls`
  and a roving `tabindex`;
- each panel is a `role="tabpanel"` with `aria-labelledby` and
  `tabindex="0"`.

**Keyboard:**
- Left and Right move between tabs, wrapping around;
- Home and End go to the first and last tab;
- Tab moves into the panel.

**Rendering:**

| Topic | Details |
| --- | --- |
| Panels | Every panel is rendered on the server. The Active tab (default: the first visible) is shown and the others are `hidden`. |
| Switching | Alpine.js (bundled with Livewire) switches panels in the browser, without requests. Its state is one inline `x-data` expression, so it also works in HTML that Livewire morphs in. |
| Livewire updates | The open tab survives them (`wire:ignore.self` on tabs and panels). |
| Identity | Each tab's id is its Name, or its Label as a slug ("Exam results" is `exam-results`). It is stable across renders, and duplicates fail validation. |
| Hidden tabs | Tabs whose content the user may not see (requirements, or nothing renders) are left out. If the Active tab is left out, the first visible tab opens. |
| Overflow | The tab list scrolls horizontally on narrow screens. |

## Accordion

```php
new Accordion(
    new AccordionItem('Fees', 'Balances and receipts', new Infolist(…)),
    new AccordionItem('Transport', new Property(AccordionItem::Open, true), …),
)
new Accordion(new Property(Accordion::Multiple, false), …)   // opening one closes the others
```

| Topic | Details |
| --- | --- |
| Markup | Items are native `<details>`/`<summary>` elements, with no JavaScript. Each summary is a keyboard-operable button (Enter, Space) announced as expanded or collapsed. |
| `Multiple` false | Uses the HTML exclusive accordion (`name`). Browsers without it keep items independent. |
| Livewire updates | Open items stay open. |
| Hidden items | Items the user may not see, or with nothing in them, are left out. |

## Collapsible

```php
new Collapsible('Advanced details', 'Rarely needed', new Infolist(…))
new Collapsible('Notes', new Property(Collapsible::Open, true), …)
```

A single native disclosure, closed by default. The chevron rotates with a
short CSS transition, which is disabled for users who prefer reduced
motion. A collapsible `Section` behaves the same way, with its header as the
summary.
