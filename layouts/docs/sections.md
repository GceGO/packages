# Sections

```php
new Section('Academic overview', 'This term so far', new Grid(…))
new Section('Students', new Property(Section::Icon, 'users'), new Actions(new Create), new Table(…), new Footer(…))
new Section('Archive', new Property(Section::Collapsible, true), new Property(Section::Collapsed, true), …)
new Section('Finance', new Permission('dashboard.finance'), …)
```

## Markup

A section with a title is a `<section aria-labelledby>` containing, in
order:
1. a header: an optional icon, then the title at the current heading
   level, then the description;
2. the header actions;
3. the body;
4. the footer.

Headings of components inside the section are one level deeper. A
section without a title is a `<div>`.

## Properties

| Property | Values |
| --- | --- |
| Label, Description | shorthand: `new Section('Title', 'Description', …)` |
| `Icon` | a gcego icon name |
| `Surface` | `card` (default: border, surface, shadow), `muted`, `none` |
| `Padding` | `none`, `sm`, `md` (default), `lg` |
| `Collapsible`, `Collapsed` | a native `<details>` disclosure; open unless `Collapsed` |
| `Empty` | text shown when nothing in the body renders (translated with `__()`) |

The body gap is 6 by default (`new Gap(…)` changes it).

## Regions

| Region | Where it renders |
| --- | --- |
| `new Actions(…)` | the header, after the title (below the summary in collapsible sections) |
| `new Footer(…)` | below the body, after a rule |

Their children keep their own hosts and authorization. An action the user
may not run renders nothing, and empty regions are left out. Outside a
section or card, `Actions` is an inline group and `Footer` a `<footer>`.

## Empty and unauthorized content

| Situation | Result |
| --- | --- |
| Nothing in the body renders (an unauthorized child, an empty provider…) | The section renders nothing at all: no empty card, no heading over nothing. |
| The same, with `Empty` set | The section shows that text instead. |
| The section has requirements | It, and everything in it, renders only when they allow. This check runs against the registered definition, so the owner's requirements are inherited. |
