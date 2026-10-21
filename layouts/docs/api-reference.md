# API reference

Namespace `Gcego\Layouts`.

## Components

Every component extends `LayoutComponent` (a gcego/morfaw `Component`
and a gcego/blueprints `PartContainer`, role `layout`).

| Class | Shorthand | Properties / children |
| --- | --- | --- |
| `Layout` | Name | Label, Description; `Gap`; `layoutName()`, `misplacedRegions()` |
| `Components\Container` | | `MaxWidth`, `Fluid`, `Padding`, `Centered`, `Element` |
| `Components\Grid` | | `MinItemWidth`, `Dense`, `Element`; `Columns`, `Gap`, `Align`; `columns()`, `spanOf()` |
| `Components\Row` | | `Wrap`, `StackBelow`, `Element`; `Gap`, `Align` |
| `Components\Stack` | | `Divided`, `Element`; `Gap`, `Align` |
| `Components\Section` | Label, Description | `Icon`, `Surface`, `Padding`, `Collapsible`, `Collapsed`, `Empty`; `Gap`; regions `Actions`, `Footer` |
| `Components\Card` | Label, Description | `Icon`, `Variant`, `Padding`, `Empty`; `Gap`; regions `Actions`, `Footer` |
| `Components\Actions`, `Components\Footer` | | `Gap`, `Align` |
| `Components\Split` | | `Ratio`, `StackBelow`; `Gap`; exactly one `Main` and one `Aside` |
| `Components\Sidebar` | | `Width`, `Sticky`, `StackBelow`; `Gap`; exactly one `Main` and one `Aside` |
| `Components\Main` | | `Element` (div, main, section, article), Label |
| `Components\Aside` | Label | `Sticky` |
| `Components\Tabs` | Label | `Active`; children `Tab` |
| `Components\Tab` | Label (required) | Name, `Icon`; `tabName()` |
| `Components\Accordion` | | `Multiple`; children `AccordionItem` |
| `Components\AccordionItem` | Label (required), Description | `Open`, `Icon` |
| `Components\Collapsible` | Label (required), Description | `Open`, `Icon` |
| `Components\Divider` | Label | `Orientation`, `Spacing`, `Decorative`; no children |
| `Components\Spacer` | Size | `Grow`; no children |
| `Components\ScrollArea` | Label | `Direction`, `MaxHeight` |
| `Components\Sticky` | | `Position`, `Offset`, `From`, `Layer`, `Surface` |
| `Components\EmptyState` | Label (required), Description | `Icon`; children are actions |
| `Components\Loading` | Label | `Variant`, `Lines`; no children |

Every component also accepts gcego/security requirements, `Gcego\Chroma\Tailwind` and
placement nodes.

### LayoutComponent

| Method | |
| --- | --- |
| `view()` | the Blade view |
| `viewData(LayoutView)` | extra view data |
| `ownsRegion(LayoutComponent)` | regions it places itself |
| `hidesWhenEmpty()`, `emptyText()` | empty behavior |
| `childHeadingLevel(RenderContext)` | children's heading level |
| `placement(child, siblings, config)` | wrapper classes for a child |
| `title()`, `description()`, `icon()`, `requirements()` | |
| `gapClasses()`, `alignClasses()` | |
| static `styleSlots()` | its styling slots |
| `blueprintRole()` | `layout`, multiple |

## Configuration and placement nodes

| Class | Signature |
| --- | --- |
| `Configuration\Columns` | `(int\|array<string, int>)`, 1–6; `auto(int $items)`, `at()`, `maximum()`, `classes()` |
| `Configuration\Gap` | `(int\|array\|null $gap = null, ?int $row = null, ?int $column = null)` |
| `Configuration\Align` | `(string\|array\|null $items = null, string\|array\|null $justify = null)` |
| `Responsive\ColumnSpan` (placement) | `(int\|string\|array)`: 1–6, `full`, `auto`; `classes(Columns)`, `largest()` |
| `Responsive\Visibility` (placement) | `(?string $hiddenBelow = null, ?string $hiddenFrom = null)` |
| `Responsive\Breakpoint` | enum `Default`, `Sm`, `Md`, `Lg`, `Xl`, `Xxl` (`2xl`); `parse()`, `names()`, `order()` |
| `Responsive\ResponsiveValue` | `of($value, $accepts, $subject, $expected)`, `at()`, `resolved()`, `classes(array $map)`, `isResponsive()` |

Placement nodes implement `Gcego\Components\Contracts\PlacementConfiguration`,
which every component accepts.

## Services

| Class | Members |
| --- | --- |
| `LayoutManager` (singleton) | `identity()`, `resolve()` (404), `authorize()` (403), `allows()`, `render(string $identity, $record = null, bool $live = false): HtmlString`, `renderLayout(LayoutComponent, ?identity, $record, $live)`, `inspect()`, `config()` |
| `Registry\LayoutKind` | the "layout" kind; `blueprintOnlyParts()` |
| `Rendering\LayoutRenderer` | the runtime renderer of `LayoutComponent` |
| `Rendering\LayoutView` | `$layout` in views: `items`, `regions`, `config`, `content()`, `class($slot, ...$computed)`, `classes()`, `id()`, `key()`, `live()`, `level()`, `title()`, `description()`, `icon()`, `regions($class)`, `region($class)` |
| `Rendering\LayoutItem` | a rendered child and its placement wrapper |
| `Presets\LayoutPresets` | literal classes and bounded scales |
| `LayoutsConfig` | validated configuration |
| `LayoutsServiceProvider` | `Components` (every component class) |

## Blade

```blade
<x-gcego-layouts::layout name="dashboard" :record="$id" :live="true" />
```

## Configuration (`config/gcego-layouts.php`)

| Key | Default |
| --- | --- |
| `container.default_max_width` | `7xl` |
| `grid.default_gap` | 6 |
| `diagnostics.max_nodes` | 400 |
| `diagnostics.max_depth` | 12 |
| `diagnostics.max_livewire_components` | 12 |

Invalid values fail when the configuration is first read.

The breakpoint list in the prompt's sample configuration was left out on
purpose: Tailwind's breakpoints are fixed at build time. Making them
configurable would generate classes the compiler cannot find.

## Security

| Concern | Behavior |
| --- | --- |
| Identities | Only registered identities render by name (`layout:<name>`). Class names, other kinds, paths and malformed names are 404. Layouts are denied unless their requirements allow (deny by default). |
| Child authorization | Never bypassed. Children render through their own renderers and hosts, which authorize themselves (widgets through the owner, tables, forms, infolists and actions through their blueprint). Layout components with requirements are checked against the registered definition, inheriting its requirements. A generic `Gcego\Components\Component` has no authorization of its own (the runtime's container renderer), so restrict content with a layout component or the content's own requirements. |
| No browser input | Views, elements, classes and components come from trusted definitions and allowlists. The browser never selects a component, view, element or class. |
| Escaping | Every text is escaped. Dynamic attributes are ids derived from paths, allowlisted element names and literal classes. |
| Snapshots | No layout state or context is serialized into Livewire snapshots; tab and disclosure state lives in the browser. |
| Visibility | `Visibility` is presentational, never a security boundary. |

## Diagnostics

`LayoutManager::inspect('dashboard')` or `inspect($component)` returns a
`Diagnostics\LayoutReport`:

| Member | |
| --- | --- |
| `identity` | |
| `nodes` | path, type, class, depth, children, slots |
| `diagnostics` | list of `LayoutDiagnostic` (check, level, message, path) |
| `isHealthy()`, `errors()`, `warnings()`, `checks()`, `toArray()` | |

Checks:

| Check | Reports |
| --- | --- |
| `schema` | validation errors |
| `registry` | unknown identity |
| `authorization` | a registered layout without requirements |
| `nesting` | misplaced regions, and blueprint-only parts in a standalone layout |
| `spans`, `visibility` | spans or visibility that are ignored |
| `identity` | duplicate sibling names (positional paths) |
| `renderer` | a component without a renderer |
| `styling` | unknown slots |
| `accessibility` | |
| `performance` | node count, depth, Livewire hosts |

Inspection runs no queries, providers, authorization or rendering, and
reports no values.

## Exceptions (`Exceptions\LayoutException`)

`InvalidLayoutException` covers invalid responsive values, configuration
nodes and configuration. Component validation errors come from
gcego/morfaw, and rendering errors are the runtime's `RenderException`.
