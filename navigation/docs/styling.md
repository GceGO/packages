# Styling

## Slots

**On `Navigation`** (for the whole navigation):

| Slot | Styles |
| --- | --- |
| `Tailwind::Wrapper` (`root`) | the `<nav>` |
| `ListSlot` | every list |
| `ItemSlot` | every link |
| `ItemActiveSlot`, `ItemInactiveSlot` | the active link / other links |
| `Tailwind::Icon`, `Tailwind::Label` | icons, labels |
| `BadgeSlot` | badges |
| `GroupSlot`, `GroupHeaderSlot`, `GroupItemsSlot` | groups, their headers, their lists |
| `HeadingSlot`, `DividerSlot` | headings, dividers |
| `DropdownSlot` | dropdown panels |
| `TopbarSlot` | the top-navigation list |
| `MobileSlot` | the drawer panel |
| `BreadcrumbSlot` | the breadcrumb list |

**On single nodes**, which override the Navigation's for that node:

| Node | Slots |
| --- | --- |
| `Item`, `ExternalLink` | `item`, `icon`, `label`, `badge` |
| `Group`, `Dropdown` | `group`, `group_header`, `group_items`, `dropdown`, `item` (its top-navigation button), `icon` |
| `Heading` | `heading` |
| `Divider` | `divider` |

```php
new Navigation('admin', …, new Tailwind(
    new Slot(Tailwind::Wrapper, ['flex', 'flex-col', 'gap-1', 'p-3']),
    new Slot(Navigation::ItemSlot, ['flex', 'items-center', 'gap-3', 'rounded-xl', 'px-3', 'py-2.5', 'text-sm', 'font-medium']),
    new Slot(Navigation::ItemActiveSlot, ['bg-indigo-600', 'text-white']),
))
new Item('Students', 'admin.students.index', new Tailwind(new Slot(Navigation::ItemSlot, ['rounded-full'])))
```

## How classes combine

1. Computed presets (`Presets\NavigationPresets`).
2. The Navigation's slot.
3. The active or inactive slot (links).
4. The node's own slot.

gcego/chroma's merger combines them, so conflicting classes replace
earlier ones. Merges are memoized per render. Unknown slots fail in
strict mode, and the inspector reports them.

## Design

| Topic | Details |
| --- | --- |
| Tokens | Colors are gcego tokens, so dark mode and themes apply. The active item uses the primary tint and color. |
| Focus | Focus rings use `focus-visible`. Transitions respect reduced motion. |
| Where classes live | Every class is a literal in the presets or the views. |

## Icons

Icons come from the shared, trusted icon set (gcego/tables `Icons`). This
package adds `home`, `users`, `academic-cap`, `inbox`, `bars-3` and
`arrow-top-right-on-square` when missing. Register more with
`app(Icons::class)->register($name, $path)`.

Unknown icon names render nothing (the inspector warns), and icons are
decorative (`aria-hidden`).
